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
ROUTE_WAYPOINTS = {   'L1': [   (14.669182, -17.437995),
              (14.669357, -17.437387),
              (14.671535, -17.437391),
              (14.675717, -17.437513),
              (14.679497, -17.437662),
              (14.679947, -17.437594),
              (14.680106, -17.437746),
              (14.680154, -17.438038),
              (14.681113, -17.438802),
              (14.684837, -17.441011),
              (14.688137, -17.441874),
              (14.691107, -17.442552),
              (14.695026, -17.442379),
              (14.719884, -17.440737),
              (14.725159, -17.4404),
              (14.728509, -17.440188),
              (14.736651, -17.439563),
              (14.738484, -17.438921),
              (14.740282, -17.437676),
              (14.741445, -17.436271),
              (14.742164, -17.434949),
              (14.74309, -17.431353),
              (14.744576, -17.424589),
              (14.745639, -17.418246),
              (14.745724, -17.413173),
              (14.745807, -17.410315),
              (14.745078, -17.407952),
              (14.743894, -17.405344),
              (14.742732, -17.402595),
              (14.742418, -17.400422),
              (14.742951, -17.397074),
              (14.743622, -17.395445),
              (14.744619, -17.393678),
              (14.745901, -17.3904),
              (14.747802, -17.383212),
              (14.749856, -17.376875),
              (14.75228, -17.371617),
              (14.754122, -17.367578),
              (14.75798, -17.353404),
              (14.759417, -17.347542),
              (14.759815, -17.344723),
              (14.760022, -17.341783),
              (14.759976, -17.337929),
              (14.759792, -17.335716),
              (14.759351, -17.332873),
              (14.758858, -17.330681),
              (14.756627, -17.32357),
              (14.755127, -17.317587),
              (14.753923, -17.309279),
              (14.753119, -17.303469),
              (14.752283, -17.299472),
              (14.749401, -17.292565),
              (14.744899, -17.282118),
              (14.744438, -17.280059),
              (14.742905, -17.274),
              (14.739035, -17.265264),
              (14.73639, -17.256343),
              (14.73317, -17.243924),
              (14.730613, -17.232917),
              (14.730376, -17.227005),
              (14.731946, -17.214418),
              (14.733224, -17.20566),
              (14.734797, -17.200586),
              (14.738602, -17.192488),
              (14.739551, -17.189994),
              (14.739822, -17.186565),
              (14.738984, -17.183384),
              (14.736732, -17.179943),
              (14.735861, -17.178271),
              (14.735262, -17.176058),
              (14.735161, -17.173514),
              (14.734913, -17.164726),
              (14.732924, -17.160646),
              (14.730493, -17.158244),
              (14.727249, -17.154057),
              (14.72574, -17.149686),
              (14.724897, -17.143454),
              (14.723784, -17.135129),
              (14.721914, -17.125447),
              (14.718959, -17.118715),
              (14.716039, -17.109084),
              (14.711336, -17.096627),
              (14.709581, -17.091547),
              (14.707123, -17.08399),
              (14.705651, -17.075617),
              (14.705371, -17.070017),
              (14.7055, -17.065917),
              (14.706097, -17.060877),
              (14.706809, -17.055796),
              (14.706339, -17.05146),
              (14.704447, -17.046758),
              (14.702548, -17.044165),
              (14.699769, -17.041815),
              (14.695538, -17.040327),
              (14.691745, -17.040503),
              (14.689559, -17.03814),
              (14.68984, -17.034967),
              (14.694272, -17.02913),
              (14.703295, -17.021958),
              (14.708324, -17.014332),
              (14.71361, -16.995565),
              (14.718938, -16.979827),
              (14.72553, -16.967554),
              (14.72664, -16.962761),
              (14.726041, -16.961427),
              (14.725564, -16.961108),
              (14.72596, -16.960762),
              (14.728335, -16.960798),
              (14.728993, -16.960465),
              (14.730404, -16.960582),
              (14.740184, -16.958388),
              (14.747648, -16.955411),
              (14.752098, -16.953636),
              (14.754713, -16.952525),
              (14.759447, -16.950769),
              (14.762386, -16.949595),
              (14.764665, -16.948696),
              (14.766992, -16.947778),
              (14.769479, -16.94679),
              (14.771708, -16.945839),
              (14.771988, -16.945668),
              (14.773297, -16.945257),
              (14.77705, -16.943753),
              (14.779054, -16.942955),
              (14.780588, -16.942153),
              (14.781556, -16.940836),
              (14.782854, -16.938819),
              (14.786698, -16.933158),
              (14.786596, -16.932439),
              (14.786551, -16.928656)],
    'L2': [   (14.669182, -17.437995),
              (14.669406, -17.43736),
              (14.672668, -17.437426),
              (14.677226, -17.43756),
              (14.679851, -17.437621),
              (14.680085, -17.43768),
              (14.680133, -17.438008),
              (14.681113, -17.438802),
              (14.685233, -17.441134),
              (14.689274, -17.442163),
              (14.691606, -17.442591),
              (14.700355, -17.442055),
              (14.724774, -17.440424),
              (14.726544, -17.440322),
              (14.736651, -17.439563),
              (14.738735, -17.438789),
              (14.740585, -17.437375),
              (14.741792, -17.435688),
              (14.74255, -17.433777),
              (14.744324, -17.425895),
              (14.74555, -17.418847),
              (14.745724, -17.413173),
              (14.745784, -17.410103),
              (14.74474, -17.4072),
              (14.743292, -17.404001),
              (14.74247, -17.401283),
              (14.742807, -17.39768),
              (14.743502, -17.395667),
              (14.744619, -17.393678),
              (14.746057, -17.389941),
              (14.748322, -17.381191),
              (14.750616, -17.375216),
              (14.753198, -17.369784),
              (14.757154, -17.356341),
              (14.759316, -17.348046),
              (14.759815, -17.344723),
              (14.760042, -17.341349),
              (14.759925, -17.33708),
              (14.759671, -17.334782),
              (14.759083, -17.33162),
              (14.758183, -17.328338),
              (14.755268, -17.318333),
              (14.753923, -17.309279),
              (14.753052, -17.303019),
              (14.751824, -17.298105),
              (14.745791, -17.284502),
              (14.744675, -17.2812),
              (14.743361, -17.275391),
              (14.739368, -17.266021),
              (14.73639, -17.256343),
              (14.732684, -17.241928),
              (14.730402, -17.231033),
              (14.730528, -17.225292),
              (14.732653, -17.208983),
              (14.734114, -17.202444),
              (14.738306, -17.193094),
              (14.739551, -17.189994),
              (14.739785, -17.186256),
              (14.738509, -17.182466),
              (14.73631, -17.179229),
              (14.735459, -17.177054),
              (14.735171, -17.17478),
              (14.735061, -17.165513),
              (14.732924, -17.160646),
              (14.729672, -17.157425),
              (14.726577, -17.152593),
              (14.725396, -17.147256),
              (14.724092, -17.137332),
              (14.722227, -17.126586),
              (14.719107, -17.119108),
              (14.716039, -17.109084),
              (14.711308, -17.096557),
              (14.708462, -17.088511),
              (14.706221, -17.079694),
              (14.70546, -17.072969),
              (14.705413, -17.067616),
              (14.705998, -17.061525),
              (14.706809, -17.055796),
              (14.706222, -17.05106),
              (14.703704, -17.045624),
              (14.701236, -17.04287),
              (14.698251, -17.040774),
              (14.695156, -17.039462),
              (14.690613, -17.03877),
              (14.687129, -17.039165),
              (14.680447, -17.041788),
              (14.672168, -17.044231),
              (14.66462, -17.045251),
              (14.656599, -17.045162),
              (14.652723, -17.04485),
              (14.647955, -17.044501),
              (14.643984, -17.044398),
              (14.641934, -17.044325),
              (14.635458, -17.044433),
              (14.626995, -17.04555),
              (14.622449, -17.046561),
              (14.615953, -17.048394),
              (14.613323, -17.049606),
              (14.610632, -17.051607),
              (14.604511, -17.057084),
              (14.592843, -17.061743),
              (14.585169, -17.060158),
              (14.578129, -17.055744),
              (14.572077, -17.054065),
              (14.533407, -17.037635),
              (14.512868, -17.022572),
              (14.49702, -17.009),
              (14.477298, -16.96665),
              (14.473644, -16.959577),
              (14.471573, -16.959353),
              (14.468074, -16.954579),
              (14.467508, -16.954397),
              (14.464101, -16.952342),
              (14.459624, -16.953778),
              (14.455462, -16.954798),
              (14.446865, -16.956017),
              (14.441943, -16.956894),
              (14.439759, -16.957282),
              (14.435545, -16.960215),
              (14.434306, -16.961388),
              (14.434039, -16.961142),
              (14.431917, -16.960188),
              (14.429257, -16.958845),
              (14.425939, -16.957281),
              (14.425389, -16.956881),
              (14.424933, -16.956546),
              (14.423904, -16.955949),
              (14.421921, -16.954597),
              (14.41956, -16.955491),
              (14.417509, -16.959767)],
    'L3': [   (14.669182, -17.437995),
              (14.669197, -17.437608),
              (14.669282, -17.437464),
              (14.669499, -17.437313),
              (14.670364, -17.437349),
              (14.672175, -17.4374),
              (14.674083, -17.437475),
              (14.675717, -17.437513),
              (14.677226, -17.43756),
              (14.679059, -17.437593),
              (14.6798, -17.437673),
              (14.679881, -17.437605),
              (14.67998, -17.437598),
              (14.680065, -17.437652),
              (14.680106, -17.437746),
              (14.680093, -17.437881),
              (14.680133, -17.438008),
              (14.680295, -17.43815),
              (14.68071, -17.438499),
              (14.683534, -17.440447),
              (14.684124, -17.440742),
              (14.684837, -17.441011),
              (14.685764, -17.44128),
              (14.687345, -17.441673),
              (14.689274, -17.442163),
              (14.690635, -17.442479),
              (14.691302, -17.442574),
              (14.691801, -17.442594),
              (14.695026, -17.442379),
              (14.700093, -17.442075),
              (14.719258, -17.440788),
              (14.722602, -17.440548),
              (14.724774, -17.440424),
              (14.725581, -17.44038),
              (14.726096, -17.440351),
              (14.728509, -17.440188),
              (14.735612, -17.439719),
              (14.736532, -17.439592),
              (14.737244, -17.439415),
              (14.737972, -17.439159),
              (14.738735, -17.438789),
              (14.739644, -17.438203),
              (14.740282, -17.437676),
              (14.740775, -17.43716),
              (14.741275, -17.436521),
              (14.7417, -17.43585),
              (14.741904, -17.435479),
              (14.742278, -17.434667),
              (14.74255, -17.433777),
              (14.74309, -17.431353),
              (14.744093, -17.426977),
              (14.744454, -17.425237),
              (14.744856, -17.423096),
              (14.745454, -17.419478),
              (14.745717, -17.417519),
              (14.745756, -17.415936),
              (14.745675, -17.412874),
              (14.745553, -17.410607),
              (14.745465, -17.409656),
              (14.745659, -17.409641),
              (14.746154, -17.409695),
              (14.746311, -17.409672),
              (14.74645, -17.40948),
              (14.746705, -17.409389),
              (14.746888, -17.409495),
              (14.74703, -17.409606),
              (14.747246, -17.409603),
              (14.747461, -17.409521),
              (14.748274, -17.409082),
              (14.749043, -17.408655),
              (14.749877, -17.408265),
              (14.749978, -17.408154),
              (14.750039, -17.408088),
              (14.750126, -17.408076),
              (14.750221, -17.408157),
              (14.750527, -17.408145),
              (14.75097, -17.408017),
              (14.751368, -17.407819),
              (14.752407, -17.407126),
              (14.754159, -17.406001),
              (14.755092, -17.405334),
              (14.756501, -17.404235),
              (14.758881, -17.402383),
              (14.759083, -17.402206),
              (14.759157, -17.402051),
              (14.759249, -17.40194),
              (14.759423, -17.401888),
              (14.759666, -17.401689),
              (14.76036, -17.401109),
              (14.760928, -17.40073),
              (14.762085, -17.400209),
              (14.762903, -17.399993),
              (14.763396, -17.399922),
              (14.764301, -17.399878),
              (14.764529, -17.399815),
              (14.764596, -17.399735),
              (14.764678, -17.3997),
              (14.764761, -17.399732),
              (14.764992, -17.399631),
              (14.766546, -17.398652),
              (14.766648, -17.398395),
              (14.766639, -17.398117),
              (14.76656, -17.397758),
              (14.766489, -17.39748),
              (14.766418, -17.397252),
              (14.766401, -17.397147),
              (14.76642, -17.39698),
              (14.76628, -17.396436),
              (14.765982, -17.395267),
              (14.76583, -17.394658),
              (14.765638, -17.393991),
              (14.765452, -17.393222),
              (14.76517, -17.391971),
              (14.765041, -17.391624),
              (14.764551, -17.390807),
              (14.764299, -17.390381),
              (14.763125, -17.388397),
              (14.762746, -17.387817),
              (14.763399, -17.387289),
              (14.764236, -17.38666)],
    'L4': [   (14.669182, -17.437995),
              (14.669357, -17.437387),
              (14.671535, -17.437391),
              (14.675717, -17.437513),
              (14.679497, -17.437662),
              (14.679947, -17.437594),
              (14.680106, -17.437746),
              (14.680154, -17.438038),
              (14.681113, -17.438802),
              (14.684837, -17.441011),
              (14.688137, -17.441874),
              (14.691107, -17.442552),
              (14.695026, -17.442379),
              (14.719884, -17.440737),
              (14.725159, -17.4404),
              (14.728509, -17.440188),
              (14.736651, -17.439563),
              (14.738484, -17.438921),
              (14.740282, -17.437676),
              (14.741445, -17.436271),
              (14.742164, -17.434949),
              (14.74309, -17.431353),
              (14.744576, -17.424589),
              (14.745639, -17.418246),
              (14.745724, -17.413173),
              (14.745807, -17.410315),
              (14.745078, -17.407952),
              (14.743894, -17.405344),
              (14.742732, -17.402595),
              (14.742418, -17.400422),
              (14.742951, -17.397074),
              (14.743622, -17.395445),
              (14.744619, -17.393678),
              (14.745901, -17.3904),
              (14.747802, -17.383212),
              (14.749856, -17.376875),
              (14.75228, -17.371617),
              (14.754122, -17.367578),
              (14.75798, -17.353404),
              (14.759417, -17.347542),
              (14.759815, -17.344723),
              (14.760022, -17.341783),
              (14.759976, -17.337929),
              (14.759792, -17.335716),
              (14.759351, -17.332873),
              (14.758858, -17.330681),
              (14.756627, -17.32357),
              (14.755127, -17.317587),
              (14.753923, -17.309279),
              (14.753119, -17.303469),
              (14.752283, -17.299472),
              (14.749401, -17.292565),
              (14.744899, -17.282118),
              (14.744438, -17.280059),
              (14.742905, -17.274),
              (14.739035, -17.265264),
              (14.73639, -17.256343),
              (14.73317, -17.243924),
              (14.730613, -17.232917),
              (14.730376, -17.227005),
              (14.731946, -17.214418),
              (14.733224, -17.20566),
              (14.734797, -17.200586),
              (14.738602, -17.192488),
              (14.739551, -17.189994),
              (14.739822, -17.186565),
              (14.738984, -17.183384),
              (14.736732, -17.179943),
              (14.735861, -17.178271),
              (14.735262, -17.176058),
              (14.735161, -17.173514),
              (14.734913, -17.164726),
              (14.732924, -17.160646),
              (14.730071, -17.157986),
              (14.72864, -17.157201),
              (14.727856, -17.158074),
              (14.728533, -17.159031),
              (14.73024, -17.159951),
              (14.731164, -17.160338),
              (14.732065, -17.158857),
              (14.732143, -17.158187),
              (14.732539, -17.157687),
              (14.734553, -17.152737),
              (14.735309, -17.150714),
              (14.736014, -17.148851),
              (14.736748, -17.14686),
              (14.737692, -17.144637),
              (14.738817, -17.142647),
              (14.740917, -17.139011),
              (14.742462, -17.137001),
              (14.744225, -17.135308),
              (14.746244, -17.133342),
              (14.748277, -17.131377),
              (14.750948, -17.128854),
              (14.755501, -17.12445),
              (14.758071, -17.121494),
              (14.760081, -17.118365),
              (14.762186, -17.113879),
              (14.764068, -17.109862),
              (14.765549, -17.106591),
              (14.76603, -17.104361),
              (14.765072, -17.101762),
              (14.763965, -17.100625),
              (14.763563, -17.099941),
              (14.762515, -17.099097),
              (14.761081, -17.098614),
              (14.760233, -17.097922),
              (14.759244, -17.097186),
              (14.75825, -17.095805),
              (14.756393, -17.095068),
              (14.75419, -17.095048),
              (14.751467, -17.09596),
              (14.749716, -17.095513),
              (14.748683, -17.095554),
              (14.747959, -17.095206),
              (14.747713, -17.09493),
              (14.747132, -17.094372),
              (14.746137, -17.093795),
              (14.74493, -17.093149),
              (14.743284, -17.091999),
              (14.742432, -17.0919),
              (14.742003, -17.092344),
              (14.740984, -17.092208),
              (14.740346, -17.091617)]}

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
