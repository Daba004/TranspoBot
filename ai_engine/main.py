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
from pathlib import Path
from dotenv import load_dotenv

# Load environment variables from .env file in the same directory as this script
env_path = Path(__file__).parent / ".env"
load_dotenv(dotenv_path=env_path)

# ============================================================
# SIMULATION STATE
# ============================================================
simulation_active = False
simulation_stats = {"ticks": 0, "incidents_generated": 0, "trips_completed": 0}
virtual_trips = {}  # vehicle_id -> {progress, start_time, ligne_info}


# Dakar region route waypoints for realistic movement
ROUTE_WAYPOINTS = {
    "L1": [  # Dakar → Thiès (via autoroute)
        (14.6937, -17.4441), (14.7050, -17.4200), (14.7180, -17.3900),
        (14.7300, -17.3500), (14.7450, -17.3000), (14.7550, -17.2500),
        (14.7650, -17.2000), (14.7750, -17.1500), (14.7800, -17.1000),
        (14.7886, -16.9260)
    ],
    "L2": [  # Dakar → Mbour (Evite de traverser la mer en suivant la côte/autoroute)
        (14.6937, -17.4441), (14.7050, -17.4200), (14.7180, -17.3900),
        (14.7200, -17.3500), (14.6900, -17.2500), (14.6500, -17.1500),
        (14.5800, -17.0500), (14.5100, -16.9800), (14.4177, -16.9599)
    ],
    "L3": [  # Plateau → Pikine (Route terrestre)
        (14.6693, -17.4380), (14.6800, -17.4400), (14.6950, -17.4400),
        (14.7100, -17.4200), (14.7500, -17.4000), (14.7645, -17.3864)
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
    # Railway standard variables
    host = os.getenv("MYSQLHOST") or os.getenv("DB_HOST", "localhost")
    user = os.getenv("MYSQLUSER") or os.getenv("DB_USER", "root")
    password = os.getenv("MYSQLPASSWORD") or os.getenv("DB_PASS", "")
    database = os.getenv("MYSQLDATABASE") or os.getenv("DB_NAME", "transpobot")
    port = os.getenv("MYSQLPORT", "3306")

    return mysql.connector.connect(
        host=host,
        user=user,
        password=password,
        database=database,
        port=int(port)
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
                
                if not waypoints:
                    # Fallback to straight line between origin and destination
                    if trip['origine_lat'] and trip['destination_lat']:
                        waypoints = [
                            (float(trip['origine_lat']), float(trip['origine_lng'])),
                            (float(trip['destination_lat']), float(trip['destination_lng']))
                        ]
                    else:
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
            
            simulation_stats["ticks"] += 1
            
            # --- Handle Virtual Simulation for idle vehicles ---
            await _update_virtual_trips(cursor)
            
            conn.commit()
            
        except Exception as e:
            print(f"[SIM ERROR] {e}")
        
        await asyncio.sleep(5)


async def _update_virtual_trips(cursor):
    """Manage in-memory 'virtual' trips for vehicles that are active but have no trip in DB."""
    global virtual_trips, simulation_active
    if not simulation_active: return

    # Get all active vehicles
    cursor.execute("SELECT v.id, v.latitude, v.longitude, v.statut FROM vehicules v WHERE v.statut = 'actif'")
    active_vehicles = {v['id']: v for v in cursor.fetchall()}

    # Get vehicles already on real trips
    cursor.execute("SELECT vehicule_id FROM trajets WHERE statut IN ('en_cours', 'planifie')")
    busy_vehicle_ids = {t['vehicule_id'] for t in cursor.fetchall()}

    # Get all lines for random assignment
    cursor.execute("SELECT id, code, nom, origine_lat, origine_lng, destination_lat, destination_lng FROM lignes")
    all_lines = cursor.fetchall()

    for vid, v in active_vehicles.items():
        if vid in busy_vehicle_ids:
            if vid in virtual_trips: del virtual_trips[vid]
            continue

        if vid not in virtual_trips:
            # Start a new virtual trip
            line = random.choice(all_lines)
            virtual_trips[vid] = {
                "progress": 0.0,
                "line": line,
                "waypoints": ROUTE_WAYPOINTS.get(line['code'], [(float(line['origine_lat']), float(line['origine_lng'])), (float(line['destination_lat']), float(line['destination_lng']))]),
                "speed": random.uniform(30, 60),
                "fuel": 100
            }
        
        # Update progress
        vt = virtual_trips[vid]
        vt['progress'] += random.uniform(0.01, 0.04) # Slower than real trips for variety
        if vt['progress'] >= 1.0:
            # Swap destination and origin to simulate return trip
            vt['progress'] = 0.0
            line = vt['line']
            vt['waypoints'] = list(reversed(vt['waypoints']))
        
        # Calculate new position
        new_lat, new_lng = _interpolate_waypoints(vt['waypoints'], vt['progress'])
        vt['current_pos'] = (new_lat, new_lng)
        vt['fuel'] = max(10, vt['fuel'] - random.uniform(0.1, 0.3))


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

# Configuration CORS Sécurisée
frontend_url = os.getenv("FRONTEND_URL", "*") 
allowed_origins = [frontend_url] if frontend_url != "*" else ["*"]

app.add_middleware(
    CORSMiddleware,
    allow_origins=allowed_origins,
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    allow_headers=["*"],
)


# ============================================================
# MODELS
# ============================================================
class ChatRequest(BaseModel):
    message: str

class TrajetCreate(BaseModel):
    ligne_id: int
    chauffeur_id: int
    vehicule_id: int
    date_heure_depart: str
    statut: str = 'planifie'
    nb_passagers: int = 0
    recette: float = 0.0


# ============================================================
# CHATBOT (LLM) — inchangé
# ============================================================
SYSTEM_PROMPT = """
Tu es un expert SQL pour l'application TranspoBot. Ton travail est de convertir les questions en langage naturel en requêtes SQL MySQL.
Tu ne dois renvoyer QUE la requête SQL, sans texte explicatif, ni balises markdown (```sql).

Structure de la base de données :
- vehicules (id, immatriculation, type ['bus','minibus','taxi'], capacite, statut ['actif','maintenance','hors_service'], kilometrage, latitude, longitude, carburant, vitesse)
- chauffeurs (id, nom, prenom, telephone, numero_permis, disponibilite [0 ou 1], vehicule_id)
- lignes (id, code, nom, origine, destination, distance_km, duree_minutes, origine_lat, origine_lng, destination_lat, destination_lng)
- trajets (id, ligne_id, chauffeur_id, vehicule_id, date_heure_depart, date_heure_arrivee, statut ['planifie','en_cours','termine','annule'], nb_passagers, recette)
- incidents (id, trajet_id, type ['panne','accident','retard','autre'], description, gravite ['faible','moyen','grave'], date_incident, resolu)
- tarifs (id, ligne_id, type_client ['normal','etudiant','senior'], prix)

Règles :
1. N'utilise que des requêtes SELECT.
2. Si la question demande un calcul (somme, moyenne, etc.), utilise (SUM, AVG, COUNT).
3. Joint les tables si nécessaire (ex: trajets avec lignes pour le nom de la ligne).
4. Retourne des noms de colonnes explicites (AS "Nom_de_colonne").
5. N'invente JAMAIS de tables ou de colonnes. Utilise EXCLUSIVEMENT le schéma ci-dessus.
6. Si la demande de l'utilisateur n'a aucun lien avec la base de données, retourne : SELECT 'Je ne possède pas cette information.' as Reponse;

Exemples de requêtes complexes (Few-Shot Prompting avec fonctions avancées) :
Question : "Quelles sont les 3 lignes les plus rentables ce mois-ci ?"
SQL : SELECT l.nom AS "Ligne", SUM(t.recette) AS "Revenus" FROM trajets t JOIN lignes l ON t.ligne_id = l.id WHERE MONTH(t.date_heure_depart) = MONTH(NOW()) AND YEAR(t.date_heure_depart) = YEAR(NOW()) GROUP BY l.nom ORDER BY SUM(t.recette) DESC LIMIT 3;

Question : "Quel est le nombre d'incidents par type sur les 30 derniers jours ?"
SQL : SELECT type AS "Type Incident", COUNT(*) AS "Nombre" FROM incidents WHERE date_incident >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY type ORDER BY COUNT(*) DESC;

Question : "Y a-t-il des chauffeurs avec une disponibilité à zéro et quel est leur véhicule actuel ?"
SQL : SELECT c.nom AS "Nom", c.prenom AS "Prenom", v.immatriculation AS "Vehicule" FROM chauffeurs c LEFT JOIN vehicules v ON c.vehicule_id = v.id WHERE c.disponibilite = 0;
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
            model=os.getenv("MODEL_NAME", "llama-3.3-70b-versatile"),
            temperature=0.0
        )
        return chat_completion.choices[0].message.content
    elif provider == "openai":
        from openai import OpenAI
        client = OpenAI(api_key=os.getenv("OPENAI_API_KEY"))
        response = client.chat.completions.create(
            model=os.getenv("MODEL_NAME", "gpt-4o-mini"),
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
            model=os.getenv("MODEL_NAME", "llama-3.3-70b-versatile"),
            temperature=0.0
        )
        return chat_completion.choices[0].message.content
    elif provider == "openai":
        from openai import OpenAI
        client = OpenAI(api_key=os.getenv("OPENAI_API_KEY"))
        response = client.chat.completions.create(
            model=os.getenv("MODEL_NAME", "gpt-4o-mini"),
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
    """Vérification avancée de sécurité pour les requêtes SQL (Lecture seule stricte)."""
    sql_upper = sql.upper()
    
    # 1. Mots-clés strictement interdits, même enfouis
    forbidden_keywords = [
        "DROP ", "DELETE ", "UPDATE ", "INSERT ", "TRUNCATE ", 
        "ALTER ", "GRANT ", "REVOKE ", "CREATE ", "EXEC ", 
        "SHOW TABLES", "SHOW DATABASES", "INTO "
    ]
    if any(cmd in sql_upper for cmd in forbidden_keywords):
        return False
        
    # 2. Séparation des multiples requêtes (interdit)
    if ";" in sql.strip().strip(";"):
        return False
        
    # 3. Doit obligatoirement commencer par SELECT
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
        
        # Merge virtual positions
        for v in vehicles:
            if v['id'] in virtual_trips:
                vt = virtual_trips[v['id']]
                v['latitude'] = vt['current_pos'][0]
                v['longitude'] = vt['current_pos'][1]
                v['vitesse'] = vt['speed']
                v['carburant'] = int(vt['fuel'])
                v['trajet_statut'] = 'en_cours'
                v['ligne_code'] = vt['line']['code']
                v['ligne_nom'] = vt['line']['nom']
        
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
# API CRUD (Trajets & Incidents) - Ajouté selon Analyse
# ============================================================
@app.post("/api/trajets")
async def create_trajet(trajet: TrajetCreate):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
            INSERT INTO trajets (ligne_id, chauffeur_id, vehicule_id, date_heure_depart, statut, nb_passagers, recette)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """, (trajet.ligne_id, trajet.chauffeur_id, trajet.vehicule_id, trajet.date_heure_depart, trajet.statut, trajet.nb_passagers, trajet.recette))
        conn.commit()
        last_id = cursor.lastrowid
        cursor.close()
        conn.close()
        return {"success": True, "id": last_id, "message": "Trajet créé avec succès"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.delete("/api/trajets/{trajet_id}")
async def delete_trajet(trajet_id: int):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        # Soft delete : on ne détruit pas l'historique métier ni les incidents
        # On passe juste le trajet en 'annule'
        cursor.execute("UPDATE trajets SET statut = 'annule' WHERE id = %s", (trajet_id,))
        conn.commit()
        affected = cursor.rowcount
        cursor.close()
        conn.close()
        if affected == 0:
            raise HTTPException(status_code=404, detail="Trajet non trouvé")
        return {"success": True, "message": "Trajet annulé avec succès (Soft Delete)"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

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
                
                # Merge virtual positions
                for v in vehicles:
                    if v['id'] in virtual_trips:
                        vt = virtual_trips[v['id']]
                        v['latitude'] = vt['current_pos'][0]
                        v['longitude'] = vt['current_pos'][1]
                        v['vitesse'] = vt['speed']
                        v['carburant'] = int(vt['fuel'])
                        v['trajet_statut'] = 'en_cours'
                        v['ligne_code'] = vt['line']['code']
                        v['ligne_nom'] = vt['line']['nom']
                
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
    port = int(os.getenv("PORT", 8000))
    # We use 8000 as default because in our Docker setup, 
    # Apache will proxy to 8000, and Railway's $PORT will be used by Apache.
    uvicorn.run(app, host="0.0.0.0", port=port)
