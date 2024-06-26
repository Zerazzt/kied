# TO RUN THIS FILE: python scriptname.py trackid outputfile
# OUTPUT: .json file with metrics
from dotenv import load_dotenv
import os
import base64
import requests 
import json
import sys

load_dotenv()

def get_audio_features(track_id):
    # get access token
    client_id = os.getenv("SPOTIFY_CLIENT_ID")
    client_secret = os.getenv("SPOTIFY_CLIENT_SECRET")
    auth_string = client_id + ":" + client_secret
    auth_bytes = auth_string.encode("utf-8")
    auth_based64 = str(base64.b64encode(auth_bytes), "utf-8")
    url = "https://accounts.spotify.com/api/token"
    headers = {
        "Authorization" : "Basic " + auth_based64,
        "Content-Type": "application/x-www-form-urlencoded"
    }
    data = {
        "grant_type" : "client_credentials"
    }
    result = requests.post(url, headers = headers, data = data)
    json_result = json.loads(result.content)
    access_token = json_result["access_token"]
    
    # get track audio features
    url =  f"https://api.spotify.com/v1/audio-features/{track_id}"
    headers = {
        "Authorization": f"Bearer {access_token}"
    }
    response = requests.get(url, headers=headers)

    if response.status_code == 200:
        audio_features = response.json()
        return audio_features
    else:
        print(f"Error: {response.status_code}")
        return None
    

# get arguments
if len(sys.argv) < 3:
    sys.exit(-1)

track_id = sys.argv[1]
output_file = sys.argv[2]

metrics = get_audio_features(track_id)

# outputs metrics to file
with open(output_file, 'w') as json_file:
    json.dump(metrics, json_file, indent=4)



