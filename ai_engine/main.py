import os
import re
import mysql.connector
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from dotenv import load_dotenv

load_dotenv()

app = FastAPI()

# Configuration CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # A restreindre en production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Configuration DB
def get_db_connection():
    return mysql.connector.connect(
        host=os.getenv("DB_HOST", "localhost"),
        user=os.getenv("DB_USER", "root"),
        password=os.getenv("DB_PASS", ""),
        database=os.getenv("DB_NAME", "transpobot")
    )

class ChatRequest(BaseModel):
    message: str

SYSTEM_PROMPT = """
Tu es un expert SQL pour l'application TranspoBot. Ton travail est de convertir les questions en langage naturel en requetes SQL MySQL.
Tu ne dois renvoyer QUE la requête SQL, sans texte explicatif, sans balises de code markdown.

Structure de la base de données :
- vehicules (id, immatriculation, type ['bus','minibus','taxi'], capacite, statut ['actif','maintenance','hors_service'], kilometrage)
- chauffeurs (id, nom, prenom, telephone, numero_permis, disponibilite [0 ou 1], vehicule_id)
- lignes (id, code, nom, origine, destination, distance_km, duree_minutes)
- trajets (id, ligne_id, chauffeur_id, vehicule_id, date_heure_depart, date_heure_arrivee, statut ['planifie','en_cours','termine','annule'], nb_passagers, recette)
- incidents (id, trajet_id, type ['panne','accident','retard','autre'], description, gravite ['faible','moyen','grave'], date_incident, resolu)

Règles :
1. N'utilise que des requêtes SELECT.
2. Si la question demande un calcul (somme, moyenne, etc.), utilise les fonctions SQL appropriées (SUM, AVG, COUNT).
3. Joint les tables si nécessaire (ex: trajets avec lignes pour le nom de la ligne).
4. Retourne les noms de colonnes explicites pour l'affichage (AS "Nom").
"""

def generate_sql(user_message: str):
    provider = os.getenv("LLM_PROVIDER", "groq")
    
    # Simulation d'appel LLM (À remplacer par un vrai appel API comme Groq ou OpenAI)
    # Pour la démo, je vais utiliser un pattern simple ou laisser le squelette prêt.
    # Dans un environnement réel, on utilise requests.post ou le SDK.
    
    if provider == "groq":
        from groq import Groq
        client = Groq(api_key=os.getenv("GROQ_API_KEY"))
        chat_completion = client.chat.completions.create(
            messages=[
                {"role": "system", "content": SYSTEM_PROMPT},
                {"role": "user", "content": user_message}
            ],
            model=os.getenv("MODEL_NAME", "mixtral-8x7b-32768"),
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
            ]
        )
        return response.choices[0].message.content
    else:
        return "SELECT 'Erreur: Provider non configuré' AS Error"

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
        
        return {
            "question": request.message,
            "sql": sql_query,
            "data": results,
            "success": True
        }
    except Exception as e:
        return {"success": False, "error": str(e)}

@app.get("/")
def read_root():
    return {"status": "TranspoBot AI Engine is running"}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
