# How to run: python3 scriptname.py metrics_file option
# Output: print description

import joblib
import json
import numpy as np
import pandas as pd
import sys


def parse_custom(json_metrics):
    amplitude = json_metrics["amplitude"]
    bits_per_sample = json_metrics["bitsPerSample"]
    bpm = json_metrics["bpm"]
    channels = json_metrics["channels"]
    duration = json_metrics["duration"]
    sample_rate = json_metrics["sampleRate"]

    df_data = pd.DataFrame({
        'Amplitude': [amplitude],
        'BitsPerSample': [bits_per_sample],
        'BPM': [bpm],
        'Channels': [channels],
        'Duration': [duration],
        'SampleRate': [sample_rate]
    })
    return df_data


def parse_spotify(json_metrics):
    energy = json_metrics["energy"]
    danceability = json_metrics["danceability"]
    speechiness = json_metrics["speechiness"]
    valence = json_metrics["valence"]

    df_data = pd.DataFrame({
        'Energy': [energy],
        'Danceability': [danceability],
        'Speechiness': [speechiness],
        'Valence': [valence]
    })

    return df_data


def get_description(file, option):
    if option == "2":
        spotify = joblib.load('/var/www/kieddemo/descriptive-subtitle-ml/files/spotify-clf.pkl')
        result = spotify.predict(parse_spotify(file))
        result_string = ' '.join(result.flatten().astype(str))
        result = result_string + " music"
    elif option == "1":
        custom = joblib.load('files/custom-clf.pkl')
        result = custom.predict(parse_custom(file))[0]
    return result


metrics_file = sys.argv[1]
option = sys.argv[2]
output_file = sys.argv[3]

if option not in ['1', '2']:
    print("Invalid option. Please provide '2' for spotify data or '1' for custom data.")
    sys.exit(1)

with open(metrics_file, 'r') as json_file:
    metrics = json.load(json_file)
    result = get_description(metrics, option)

    with open(output_file, 'w') as file:
        file.write(result)
