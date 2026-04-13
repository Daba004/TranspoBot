import os
import re
import json
import random
import asyncio
import mysql.connector
from datetime import datetime, timedelta
from contextlib import asynccontextmanager
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import StreamingResponse
from pydantic import BaseModel
from dotenv import load_dotenv

load_dotenv()

# ============================================================
# SIMULATION STATE
# ============================================================
simulation_active = False
simulation_stats = {"ticks": 0, "incidents_generated": 0, "trips_completed": 0}

# Dakar region route waypoints for realistic movement
ROUTE_WAYPOINTS = {
    "L1": [  # Dakar → Thiès (via autoroute)
        (14.6937, -17.4441), (14.7050, -17.4200), (14.7180, -17.3900),
        (14.7300, -17.3500), (14.7450, -17.3000), (14.7550, -17.2500),
        (14.7650, -17.2000), (14.7750, -17.1500), (14.7800, -17.1000),
        (14.7886, -16.9260)
    ],
    "L4": [  # Dakar Plateau → Aéroport AIBD
        (14.6693, -17.4380), (14.6800, -17.4400), (14.6950, -17.4500),
        (14.7100, -17.4600), (14.7200, -17.4700), (14.7300, -17.4800),
        (14.7397, -17.4902)
    ]
}

INCIDENT_TYPES = [
    {"type": "retard", "descriptions": [
        "Embouteillage important sur l'autoroute",
        "Ralentissement a cause de travaux",
        "Trafic dense au niveau du peage",
        "Bouchon a l'entree de la ville"
    ], "gravite": "faible"},
    {"type": "panne", "descriptions": [
        "Surchauffe moteur detectee",
        "Probleme de freins signale",
        "Panne de climatisation",
        "Fuite d'huile detectee"
    ], "gravite": "moyen"},
    {"type": "accident", "descriptions": [
        "Accrochage mineur en voie rapide",
        "Collision evitee de justesse",
    ], "gravite": "grave"},
    {"type": "autre", "descriptions": [
        "Passager malade a bord",
        "Arret d'urgence demande",
        "Objet suspect signale"
    ], "gravite": "moyen"}
]


# ============================================================
# DATABASE
# ============================================================
def get_db_connection():
    return mysql.connector.connect(
        host=os.getenv("DB_HOST", "localhost"),
        user=os.getenv("DB_USER", "root"),
        password=os.getenv("DB_PASS", ""),
        database=os.getenv("DB_NAME", "transpobot")
    )


# ============================================================
# SIMULATION ENGINE
# ============================================================
async def run_simulation():
    """Background loop that simulates fleet movement every 5 seconds."""
    global simulation_active, simulation_stats
    
    while True:
        if not simulation_active:
            await asyncio.sleep(1)
            continue
        
        try:
            conn = get_db_connection()
            cursor = conn.cursor(dictionary=True)
            
            # 1. Get active trips with vehicle & line info
            cursor.execute("""
                SELECT t.id as trajet_id, t.ligne_id, t.vehicule_id, t.statut as trajet_statut,
                       t.date_heure_depart, t.nb_passagers,
                       v.id as vid, v.latitude, v.longitude, v.carburant, v.vitesse, v.kilometrage, v.immatriculation,
                       l.code as ligne_code, l.distance_km, l.duree_minutes,
                       l.origine_lat, l.origine_lng, l.destination_lat, l.destination_lng
                FROM trajets t
                JOIN vehicules v ON t.vehicule_id = v.id
                JOIN lignes l ON t.ligne_id = l.id
                WHERE t.statut IN ('en_cours', 'planifie')
                AND v.statut = 'actif'
            """)
            active_trips = cursor.fetchall()
            
            for trip in active_trips:
                ligne_code = trip['ligne_code']
                waypoints = ROUTE_WAYPOINTS.get(ligne_code)
                
                if not waypoints or not trip['origine_lat']:
                    continue
                
                # --- Handle planned trips: start them if departure time has passed ---
                if trip['trajet_statut'] == 'planifie':
                    if trip['date_heure_depart'] and trip['date_heure_depart'] <= datetime.now():
                        cursor.execute(
                            "UPDATE trajets SET statut='en_cours' WHERE id=%s",
                            (trip['trajet_id'],)
                        )
                        cursor.execute(
                            "UPDATE vehicules SET latitude=%s, longitude=%s, vitesse=%s WHERE id=%s",
                            (trip['origine_lat'], trip['origine_lng'], random.uniform(30, 60), trip['vid'])
                        )
                    continue
                
                # --- Move vehicle along route ---
                current_lat = float(trip['latitude']) if trip['latitude'] else float(trip['origine_lat'])
                current_lng = float(trip['longitude']) if trip['longitude'] else float(trip['origine_lng'])
                dest_lat = float(trip['destination_lat'])
                dest_lng = float(trip['destination_lng'])
                
                # Find progress along waypoints
                progress = _calculate_progress(current_lat, current_lng, waypoints)
                
                # Advance by ~2-8% per tick (simulates ~30-60 km/h in compressed time)
                speed_factor = random.uniform(0.02, 0.08)
                new_progress = min(1.0, progress + speed_factor)
                
                if new_progress >= 0.98:
                    # Trip complete!
                    cursor.execute(
                        "UPDATE trajets SET statut='termine', date_heure_arrivee=NOW() WHERE id=%s",
                        (trip['trajet_id'],)
                    )
                    cursor.execute(
                        "UPDATE vehicules SET latitude=%s, longitude=%s, vitesse=0 WHERE id=%s",
                        (dest_lat, dest_lng, trip['vid'])
                    )
                    simulation_stats["trips_completed"] += 1
                    
                    # Auto-create return trip after a delay
                    _create_return_trip(cursor, trip)
                else:
                    # Interpolate position along waypoints
                    new_lat, new_lng = _interpolate_waypoints(waypoints, new_progress)
                    speed = random.uniform(25, 75)
                    fuel_consumption = random.uniform(0.1, 0.5)
                    km_increment = random.uniform(0.3, 1.5)
                    
                    new_fuel = max(0, (trip['carburant'] or 100) - fuel_consumption)
                    new_km = (trip['kilometrage'] or 0) + km_increment
                    
                    cursor.execute("""
                        UPDATE vehicules 
                        SET latitude=%s, longitude=%s, vitesse=%s, carburant=%s, kilometrage=%s
                        WHERE id=%s
                    """, (new_lat, new_lng, speed, int(new_fuel), int(new_km), trip['vid']))
                    
                    # Random incident generation (~2% chance per tick)
                    if random.random() < 0.02 and new_fuel > 5:
                        _generate_random_incident(cursor, trip['trajet_id'])
                        simulation_stats["incidents_generated"] += 1
            
            conn.commit()
            cursor.close()
            conn.close()
            simulation_stats["ticks"] += 1
            
        except Exception as e:
            print(f"[SIM ERROR] {e}")
        
        await asyncio.sleep(5)


def _calculate_progress(lat, lng, waypoints):
    """Estimate how far along the waypoint path the vehicle currently is."""
    if not waypoints:
        return 0.0
    
    total_dist = 0
    closest_dist = float('inf')
    closest_progress = 0.0
    accumulated = 0
    
    # Calculate total route length
    for i in range(len(waypoints) - 1):
        segment = _dist(waypoints[i], waypoints[i + 1])
        total_dist += segment
    
    if total_dist == 0:
        return 0.0
    
    # Find closest segment
    accumulated = 0
    for i in range(len(waypoints) - 1):
        segment_len = _dist(waypoints[i], waypoints[i + 1])
        d = _dist((lat, lng), waypoints[i])
        if d < closest_dist:
            closest_dist = d
            closest_progress = accumulated / total_dist
        accumulated += segment_len
    
    # Check last point too
    d = _dist((lat, lng), waypoints[-1])
    if d < closest_dist:
        closest_progress = 1.0
    
    return closest_progress


def _interpolate_waypoints(waypoints, progress):
    """Get lat/lng at a given progress (0.0-1.0) along waypoint path."""
    if progress <= 0:
        return waypoints[0]
    if progress >= 1:
        return waypoints[-1]
    
    # Calculate total length
    segments = []
    total = 0
    for i in range(len(waypoints) - 1):
        d = _dist(waypoints[i], waypoints[i + 1])
        segments.append(d)
        total += d
    
    if total == 0:
        return waypoints[0]
    
    target = progress * total
    accumulated = 0
    
    for i, seg_len in enumerate(segments):
        if accumulated + seg_len >= target:
            # Interpolate within this segment
            t = (target - accumulated) / seg_len if seg_len > 0 else 0
            lat = waypoints[i][0] + t * (waypoints[i + 1][0] - waypoints[i][0])
            lng = waypoints[i][1] + t * (waypoints[i + 1][1] - waypoints[i][1])
            return (round(lat, 7), round(lng, 7))
        accumulated += seg_len
    
    return waypoints[-1]


def _dist(p1, p2):
    """Simple euclidean distance (sufficient for small areas)."""
    return ((p1[0] - p2[0]) ** 2 + (p1[1] - p2[1]) ** 2) ** 0.5


def _create_return_trip(cursor, trip):
    """Create a new planned trip for the vehicle to return."""
    try:
        depart_time = datetime.now() + timedelta(minutes=random.randint(2, 10))
        passengers = random.randint(10, int(trip.get('nb_passagers', 40) or 40))
        recette = passengers * random.choice([1500, 2500])
        
        cursor.execute("""
            INSERT INTO trajets (ligne_id, chauffeur_id, vehicule_id, date_heure_depart, statut, nb_passagers, recette)
            SELECT %s, chauffeur_id, vehicule_id, %s, 'planifie', %s, %s
            FROM trajets WHERE id = %s
        """, (trip['ligne_id'], depart_time, passengers, recette, trip['trajet_id']))
    except Exception as e:
        print(f"[RETURN TRIP ERROR] {e}")


def _generate_random_incident(cursor, trajet_id):
    """Generate a random incident for a trip."""
    try:
        incident_type = random.choice(INCIDENT_TYPES)
        description = random.choice(incident_type["descriptions"])
        cursor.execute("""
            INSERT INTO incidents (trajet_id, type, description, gravite, date_incident, resolu)
            VALUES (%s, %s, %s, %s, NOW(), FALSE)
        """, (trajet_id, incident_type["type"], description, incident_type["gravite"]))
    except Exception as e:
        print(f"[INCIDENT ERROR] {e}")


# ============================================================
# APP LIFECYCLE
# ============================================================
@asynccontextmanager
async def lifespan(app: FastAPI):
    """Start the simulation background task on app startup."""
    task = asyncio.create_task(run_simulation())
    print("[OK] TranspoBot Simulation Engine started")
    yield
    task.cancel()
    print("[STOP] Simulation Engine stopped")

app = FastAPI(lifespan=lifespan)

# Configuration CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # A restreindre en production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# ============================================================
# MODELS
# ============================================================
class ChatRequest(BaseModel):
    message: str


# ============================================================
# CHATBOT (LLM) — inchangé
# ============================================================
SYSTEM_PROMPT = """
Tu es un expert SQL pour l'application TranspoBot. Ton travail est de convertir les questions en langage naturel en requetes SQL MySQL.
Tu ne dois renvoyer QUE la requête SQL, sans texte explicatif, sans balises de code markdown.

Structure de la base de données :
- vehicules (id, immatriculation, type ['bus','minibus','taxi'], capacite, statut ['actif','maintenance','hors_service'], kilometrage, latitude, longitude, carburant, vitesse)
- chauffeurs (id, nom, prenom, telephone, numero_permis, disponibilite [0 ou 1], vehicule_id)
- lignes (id, code, nom, origine, destination, distance_km, duree_minutes, origine_lat, origine_lng, destination_lat, destination_lng)
- trajets (id, ligne_id, chauffeur_id, vehicule_id, date_heure_depart, date_heure_arrivee, statut ['planifie','en_cours','termine','annule'], nb_passagers, recette)
- incidents (id, trajet_id, type ['panne','accident','retard','autre'], description, gravite ['faible','moyen','grave'], date_incident, resolu)
- tarifs (id, ligne_id, type_client ['normal','etudiant','senior'], prix)

Règles :
1. N'utilise que des requêtes SELECT.
2. Si la question demande un calcul (somme, moyenne, etc.), utilise les fonctions SQL appropriées (SUM, AVG, COUNT).
3. Joint les tables si nécessaire (ex: trajets avec lignes pour le nom de la ligne).
4. Retourne les noms de colonnes explicites pour l'affichage (AS "Nom").
5. N'invente JAMAIS de tables ou de colonnes. Utilise EXCLUSIVEMENT le schéma ci-dessus.
6. Si la demande de l'utilisateur n'a aucun lien avec la base de données, retourne : SELECT 'Je ne possède pas cette information.' as Reponse;
"""

def generate_sql(user_message: str):
    provider = os.getenv("LLM_PROVIDER", "groq")
    
    if provider == "groq":
        from groq import Groq
        client = Groq(api_key=os.getenv("GROQ_API_KEY"))
        chat_completion = client.chat.completions.create(
            messages=[
                {"role": "system", "content": SYSTEM_PROMPT},
                {"role": "user", "content": user_message}
            ],
            model=os.getenv("MODEL_NAME", "llama-3.1-8b-instant"),
            temperature=0.0
        )
        return chat_completion.choices[0].message.content
    elif provider == "openai":
        from openai import OpenAI
        client = OpenAI(api_key=os.getenv("OPENAI_API_KEY"))
        response = client.chat.completions.create(
            model=os.getenv("MODEL_NAME", "gpt-3.5-turbo"),
            messages=[
                {"role": "system", "content": SYSTEM_PROMPT},
                {"role": "user", "content": user_message}
            ],
            temperature=0.0
        )
        return response.choices[0].message.content
    else:
        return "SELECT 'Erreur: Provider non configuré' AS Error"

def generate_response(user_message: str, sql_query: str, data: list):
    provider = os.getenv("LLM_PROVIDER", "groq")
    
    system_prompt = """
Tu es l'assistant IA de TranspoBot. Ton rôle est d'analyser les résultats de la base de données pour formuler une réponse concise, claire et naturelle en français répondant directement à la question de l'utilisateur.
Ne mentionne jamais que tu as utilisé SQL, ne mentionne pas la requête SQL, et ne parle pas de la structure de base de données. Donne directement la réponse.
Si les données (data) sont vides, informe gentiment qu'aucune information n'a été trouvée pour la requête.
RÈGLE ABSOLUE : Tous les montants d'argent et les recettes sont TOUJOURS en Francs CFA (FCFA). N'utilise JAMAIS le mot Euro, €, Dollar ou $.
"""
    
    # Raccourcir les données si elles sont trop volumineuses
    data_str = str(data)
    if len(data_str) > 2000:
        data_str = data_str[:2000] + "... (données tronquées)"
        
    content = f"Question : {user_message}\nDonnées récupérées : {data_str}"

    if provider == "groq":
        from groq import Groq
        client = Groq(api_key=os.getenv("GROQ_API_KEY"))
        chat_completion = client.chat.completions.create(
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": content}
            ],
            model=os.getenv("MODEL_NAME", "llama-3.1-8b-instant"),
            temperature=0.0
        )
        return chat_completion.choices[0].message.content
    elif provider == "openai":
        from openai import OpenAI
        client = OpenAI(api_key=os.getenv("OPENAI_API_KEY"))
        response = client.chat.completions.create(
            model=os.getenv("MODEL_NAME", "gpt-3.5-turbo"),
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": content}
            ],
            temperature=0.0
        )
        return response.choices[0].message.content
    else:
        return "La réponse n'a pu être générée car le fournisseur LLM n'est pas configuré."

def is_safe_sql(sql: str) -> bool:
    # Vérification basique de sécurité
    sql_upper = sql.upper()
    forbidden = ["DROP ", "DELETE ", "UPDATE ", "INSERT ", "TRUNCATE ", "ALTER ", "GRANT "]
    if any(cmd in sql_upper for cmd in forbidden):
        return False
    # Doit commencer par SELECT
    if not sql_upper.strip().startswith("SELECT"):
        return False
    return True


# ============================================================
# CHATBOT ENDPOINTS
# ============================================================
@app.post("/ask")
async def ask_assistant(request: ChatRequest):
    try:
        # 1. Générer SQL
        sql_query = generate_sql(request.message)
        
        # Nettoyage du SQL (enlever les markdown ```sql si présents)
        sql_query = re.sub(r'```sql|```', '', sql_query).strip()
        
        # 2. Sécurité
        if not is_safe_sql(sql_query):
            raise HTTPException(status_code=403, detail="Requête SQL non autorisée (Lecture seule uniquement)")
        
        # 3. Exécuter SQL
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute(sql_query)
        results = cursor.fetchall()
        cursor.close()
        conn.close()
        
        # 4. Générer la réponse naturelle
        reponse_texte = generate_response(request.message, sql_query, results)
        
        return {
            "question": request.message,
            "sql": sql_query,
            "data": results,
            "reponse": reponse_texte,
            "success": True
        }
    except mysql.connector.Error as db_err:
        return {"success": False, "error": "Je n'ai pas pu comprendre votre requête ou les données sont introuvables. Pouvez-vous reformuler ?"}
    except Exception as e:
        return {"success": False, "error": str(e)}


# ============================================================
# SIMULATION API ENDPOINTS
# ============================================================
@app.get("/api/vehicles/positions")
async def get_vehicle_positions():
    """Return current positions and status of all vehicles."""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT v.id, v.immatriculation, v.type, v.statut, v.latitude, v.longitude,
                   v.carburant, v.vitesse, v.kilometrage,
                   c.nom as chauffeur_nom, c.prenom as chauffeur_prenom,
                   t.id as trajet_id, t.statut as trajet_statut,
                   l.code as ligne_code, l.nom as ligne_nom,
                   l.origine, l.destination,
                   l.origine_lat, l.origine_lng, l.destination_lat, l.destination_lng
            FROM vehicules v
            LEFT JOIN chauffeurs c ON c.vehicule_id = v.id
            LEFT JOIN trajets t ON t.vehicule_id = v.id AND t.statut IN ('en_cours', 'planifie')
            LEFT JOIN lignes l ON t.ligne_id = l.id
            ORDER BY v.id
        """)
        vehicles = cursor.fetchall()
        
        # Convert Decimal types to float for JSON serialization
        for v in vehicles:
            for key in ['latitude', 'longitude', 'vitesse']:
                if v[key] is not None:
                    v[key] = float(v[key])
            for key in ['origine_lat', 'origine_lng', 'destination_lat', 'destination_lng']:
                if v.get(key) is not None:
                    v[key] = float(v[key])
        
        cursor.close()
        conn.close()
        return {"vehicles": vehicles, "simulation_active": simulation_active}
    except Exception as e:
        return {"vehicles": [], "error": str(e)}


@app.get("/api/lines")
async def get_lines():
    """Return all lines with their GPS coordinates for map display."""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT id, code, nom, origine, destination, distance_km, duree_minutes,
                   origine_lat, origine_lng, destination_lat, destination_lng
            FROM lignes
        """)
        lines = cursor.fetchall()
        
        for line in lines:
            for key in ['origine_lat', 'origine_lng', 'destination_lat', 'destination_lng', 'distance_km']:
                if line[key] is not None:
                    line[key] = float(line[key])
            # Attach waypoints if available
            code = line['code']
            if code in ROUTE_WAYPOINTS:
                line['waypoints'] = [{"lat": wp[0], "lng": wp[1]} for wp in ROUTE_WAYPOINTS[code]]
        
        cursor.close()
        conn.close()
        return {"lines": lines}
    except Exception as e:
        return {"lines": [], "error": str(e)}


@app.get("/api/simulation/status")
async def simulation_status():
    """Return simulation status and stats."""
    return {
        "active": simulation_active,
        "stats": simulation_stats
    }


@app.post("/api/simulation/toggle")
async def toggle_simulation():
    """Start or stop the simulation."""
    global simulation_active
    simulation_active = not simulation_active
    return {
        "active": simulation_active,
        "message": "Simulation activée" if simulation_active else "Simulation arrêtée"
    }


@app.get("/api/recent-incidents")
async def get_recent_incidents():
    """Return incidents from the last hour for live alerts."""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT i.*, l.nom as ligne_nom, v.immatriculation
            FROM incidents i
            JOIN trajets t ON i.trajet_id = t.id
            JOIN lignes l ON t.ligne_id = l.id
            JOIN vehicules v ON t.vehicule_id = v.id
            WHERE i.date_incident >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND i.resolu = FALSE
            ORDER BY i.date_incident DESC
            LIMIT 5
        """)
        incidents = cursor.fetchall()
        
        # Convert datetime objects to strings for JSON serialization
        for inc in incidents:
            if inc.get('date_incident'):
                inc['date_incident'] = inc['date_incident'].strftime('%Y-%m-%d %H:%M:%S')
        
        cursor.close()
        conn.close()
        return {"incidents": incidents}
    except Exception as e:
        return {"incidents": [], "error": str(e)}


# ============================================================
# SSE (Server-Sent Events) STREAM
# ============================================================
@app.get("/stream")
async def stream_positions():
    """SSE endpoint that pushes vehicle positions every 3 seconds."""
    
    async def event_generator():
        last_incident_count = 0
        while True:
            try:
                conn = get_db_connection()
                cursor = conn.cursor(dictionary=True)
                
                # Get vehicle positions with full details
                cursor.execute("""
                    SELECT v.id, v.immatriculation, v.type, v.statut, 
                           v.latitude, v.longitude, v.carburant, v.vitesse, v.kilometrage,
                           c.nom as chauffeur_nom, c.prenom as chauffeur_prenom,
                           t.id as trajet_id, t.statut as trajet_statut,
                           l.code as ligne_code, l.nom as ligne_nom,
                           l.origine, l.destination
                    FROM vehicules v
                    LEFT JOIN chauffeurs c ON c.vehicule_id = v.id
                    LEFT JOIN trajets t ON t.vehicule_id = v.id AND t.statut IN ('en_cours', 'planifie')
                    LEFT JOIN lignes l ON t.ligne_id = l.id
                """)
                vehicles = cursor.fetchall()
                
                for v in vehicles:
                    for key in ['latitude', 'longitude', 'vitesse']:
                        if v[key] is not None:
                            v[key] = float(v[key])
                
                # Check for new incidents and get active count
                cursor.execute("SELECT COUNT(*) as cnt FROM incidents WHERE resolu = FALSE")
                incident_count = cursor.fetchone()['cnt']
                new_incidents = incident_count > last_incident_count
                last_incident_count = incident_count
                
                # Get global stats for dashboard
                cursor.execute("SELECT SUM(recette) as total_recette FROM trajets")
                total_recette = float(cursor.fetchone()['total_recette'] or 0)
                
                cursor.execute("SELECT COUNT(*) as finished_trips FROM trajets WHERE statut = 'termine'")
                finished_trips = cursor.fetchone()['finished_trips']
                
                cursor.execute("SELECT COUNT(*) as active_incidents FROM incidents WHERE resolu = FALSE")
                active_incidents_count = cursor.fetchone()['active_incidents'] 
                
                cursor.execute("SELECT COUNT(*) as total_incidents FROM incidents")
                total_incidents = cursor.fetchone()['total_incidents']
                
                cursor.execute("SELECT COUNT(*) as grave_incidents FROM incidents WHERE gravite = 'grave' AND resolu = FALSE")
                grave_incidents = cursor.fetchone()['grave_incidents']
                
                cursor.execute("SELECT COUNT(*) as available_chauffeurs FROM chauffeurs WHERE disponibilite = 1")
                available_chauffeurs = cursor.fetchone()['available_chauffeurs']
                
                cursor.close()
                conn.close()
                
                data = {
                    "vehicles": vehicles,
                    "stats": {
                        "total_recette": total_recette,
                        "finished_trips": finished_trips,
                        "active_incidents": active_incidents_count,
                        "total_incidents": total_incidents,
                        "grave_incidents": grave_incidents,
                        "available_chauffeurs": available_chauffeurs,
                        "total_vehicles": len(vehicles)
                    },
                    "simulation_active": simulation_active,
                    "new_incident": new_incidents,
                    "timestamp": datetime.now().strftime('%H:%M:%S')
                }
                
                # Serialize datetime objects
                yield f"data: {json.dumps(data, default=str)}\n\n"
                
            except Exception as e:
                yield f"data: {json.dumps({'error': str(e)})}\n\n"
            
            await asyncio.sleep(3)
    
    return StreamingResponse(
        event_generator(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
            "X-Accel-Buffering": "no"
        }
    )


# ============================================================
# ROOT
# ============================================================
@app.get("/")
def read_root():
    return {"status": "TranspoBot AI Engine is running", "simulation": simulation_active}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
