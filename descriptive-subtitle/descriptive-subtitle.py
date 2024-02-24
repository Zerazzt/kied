from openai import OpenAI
from dotenv import load_dotenv
import json
import os
load_dotenv()

def get_description(metrics):
    client = OpenAI(api_key=os.getenv("GPT_API_KEY"))
    metrics = json.dumps(metrics)
    prompt = '''Return a short descriptive phrase of this sound based on the following metrics, between 5 - 8 words, includes the word "music", don't include metric variables, no verbs, don't include numbers, must capture mood of the song: '''
    prompt = prompt + metrics
    response = client.chat.completions.create(
        model="gpt-3.5-turbo-0125",
        response_format={ "type": "json_object" },
        messages=[
            {"role": "system", "content": "You are a helpful assistant designed to output JSON."},
            {"role": "user", "content": prompt}
        ]
    )
    content = json.loads(response.choices[0].message.content)
    description = content["description"] 
    return description
