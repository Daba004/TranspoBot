# TranspoBot

Application de gestion de transport urbain avec un assistant IA qui traduit les demandes en langage naturel en requêtes SQL. Le système combine une interface web temps réel (suivi de flotte sur carte, tableaux de bord) et un moteur de simulation de trajets, le tout déployable via Docker.

---

## Démo

> **Lien en ligne** : À compléter (ex. Railway, Render, ou VPS)

### Exemples de questions à l'assistant

| Question | Résultat |
|----------|----------|
| `"Quelle est la recette totale ?"` | `1 027 000 F` (somme des recettes de la table `trajets`) |
| `"Liste des incidents critiques non résolus"` | Tableau des incidents `gravite = 'grave'` et `resolu = FALSE` avec ligne, véhicule, description |
| `"Quels sont les chauffeurs disponibles ?"` | Liste des chauffeurs avec `disponibilite = 1` et leur véhicule assigné |

---

## Fonctionnalités principales

- **Assistant IA (chatbot)** : langage naturel → SQL → réponse naturelle (Groq Llama 3.1 / OpenAI)
- **Suivi temps réel** : carte Leaflet avec positions véhicules (SSE, mise à jour 3 s)
- **Simulation de flotte** : déplacement véhicules le long de waypoints, génération incidents aléatoires, création trajets retour
- **Tableaux de bord** : stats globales, historique trajets, gestion incidents, tarifs par ligne
- **API REST** : `/ask` (chat), `/stream` (SSE), `/api/vehicles/positions`, `/api/lines`, `/api/simulation/*`

---

## Architecture

```
┌─────────────┐      HTTP/JSON      ┌──────────────┐      SQL      ┌────────────┐
│  Navigateur │ ──────────────────▶ │  Apache/PHP  │ ◀──────────── │  MySQL     │
│  (Tailwind, │                     │  (public/)   │                │  (transpo- │
│   Leaflet)  │                     │              │                │   bot)     │
└──────┬──────┘                     └──────┬───────┘                └────────────┘
       │                                   │
       │  /ask, /stream, /api/*            │
       │  (proxifié par Apache)            │
       ▼                                   ▼
┌─────────────────────────────────────────────────────────────┐
│                    FastAPI (Python) — port 8000             │
│  • generate_sql()  — LLM (Groq/OpenAI) → requête SELECT     │
│  • is_safe_sql()   — validation lecture seule               │
│  • generate_response() — LLM → réponse naturelle            │
│  • Simulation engine — background task (asyncio, 5 s tick)  │
└─────────────────────────────────────────────────────────────┘
```

**Flux IA** : question utilisateur → `generate_sql()` (system prompt + schéma DB) → requête `SELECT` seule → exécution MySQL → `generate_response()` (données → phrase française, montants en **FCFA**).

---

## Stack technique

| Couche | Technologie |
|--------|-------------|
| Frontend | PHP 8.2, Apache, Tailwind CSS (via CDN), Leaflet 1.9, Vanilla JS (SSE) |
| Backend IA | Python 3.11, FastAPI, Uvicorn, Pydantic, mysql-connector-python |
| LLM | **Groq** (par défaut) : `llama-3.1-8b-instant` · Optionnel : OpenAI `gpt-4o-mini` |
| Base de données | MySQL / MariaDB (schéma 7 tables : `vehicules`, `chauffeurs`, `lignes`, `trajets`, `incidents`, `tarifs`) |
| Déploiement | Docker (php:8.2-apache + python3), `entrypoint.sh` lance Apache (foreground) + FastAPI (background) |
| Proxy | Apache `mod_proxy` → `127.0.0.1:8000` pour `/ask`, `/stream`, `/api/*` |

---

## Installation et lancement

### Avec Docker (recommandé)

```bash
# 1. Cloner
git clone <url-du-repo> TranspoBot
cd TranspoBot

# 2. Configurer les variables d'environnement (API LLM)
cp ai_engine/.env.example ai_engine/.env   # puis éditer GROQ_API_KEY ou OPENAI_API_KEY

# 3. Construire et lancer
docker build -t transpobot .
docker run -d -p 8080:80 --name transpobot transpobot
# L'application est dispo sur http://localhost:8080
```

### Sans Docker (développement local)

```bash
# Prérequis : PHP 8.2+, Python 3.11+, MySQL/MariaDB, Composer (optionnel)

# 1. Base de données
mysql -u root -p < schema.sql   # crée la DB 'transpobot' + tables + données test

# 2. Backend IA
cd ai_engine
python3 -m venv venv
source venv/bin/activate        # Windows: venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env            # éditer GROQ_API_KEY
uvicorn main:app --reload --port 8000

# 3. Frontend (Apache/PHP)
# Configurer un VirtualHost pointant DocumentRoot vers public/
# Activer mod_proxy, mod_proxy_http, mod_rewrite
# Ajouter les règles ProxyPass (voir 000-default.conf) vers http://127.0.0.1:8000
# Redémarrer Apache
```

> **Note** : Le fichier `ai_engine/.env` contient déjà une clé Groq de démo. Remplacez-la par la vôtre pour un usage durable.

---

## Structure du dépôt

```
TranspoBot/
├── ai_engine/                 # Backend FastAPI + IA
│   ├── main.py               # App FastAPI : endpoints /ask, /stream, /api/*, simulation
│   ├── requirements.txt      # Dépendances Python
│   └── .env                  # Variables d'env (DB, LLM_PROVIDER, GROQ_API_KEY, MODEL_NAME)
├── public/                    # Frontend PHP (DocumentRoot Apache)
│   ├── index.php             # Dashboard principal (stats, trajets récents)
│   ├── tracking.php          # Carte temps réel (Leaflet) + panneau flotte
│   ├── flotte.php            # Liste véhicules
│   ├── historique.php        # Historique trajets + exports
│   ├── incidents.php         # Gestion incidents
│   ├── chauffeurs.php        # Liste chauffeurs
│   ├── assets/
│   │   └── js/
│   │       ├── chat.js       # Client chatbot (fetch /ask, SSE status)
│   │       ├── tracking.js   # Carte + markers véhicules + SSE
│   │       └── realtime.js   # Mise à jour stats/tableaux via SSE (/stream)
│   └── includes/
│       ├── db.php            # Connexion PDO MySQL
│       ├── header.php        # Layout + Tailwind CDN
│       ├── footer.php
│       ├── chatbot.php       # HTML panel chatbot (FAB + modal)
│       └── details_modal.php # Modal détails trajet
├── schema.sql                # Schéma complet MySQL + données de test
├── Dockerfile                # Image php:8.2-apache + python3 + deps
├── entrypoint.sh             # Lance FastAPI (bg) + Apache (fg), fix mpm_prefork
├── 000-default.conf          # Config Apache + ProxyPass vers FastAPI:8000
├── MCD.png                   # Modèle Conceptuel de Données
├── MLD.png                   | Modèle Logique de Données
└── Cahier_des_Charges_TranspoBot_GLSi.pdf
```

---

## Limites connues & pistes d'amélioration

1. **Sécurité SQL** : validation `is_safe_sql()` basique (liste noire mots-clés). Pas de parseur AST — risque d'injection via commentaires ou cas Unicode.
2. **Gestion d'erreurs LLM** : pas de retry/backoff si l'API Groq/OpenAI échoue ; pas de fallback local (ex. règles heuristiques).
3. **Concurrency** : simulation + endpoints partagent la même connexion MySQL (pas de pool) — peut saturer sous charge.
4. **Authentification/Autorisation** : aucune (tout public). À ajouter : JWT, rôles (admin/opérateur), rate-limiting sur `/ask`.
5. **Observabilité** : logs basiques (`print`), pas de métriques (Prometheus), pas de tracing distribué.
6. **Tests** : absence de tests unitaires / d'intégration (pytest, PHPUnit).
7. **Migrations DB** : `schema.sql` tout-en-un, pas de versionnement (Flyway / Alembic).