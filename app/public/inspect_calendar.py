import requests
from bs4 import BeautifulSoup
import re

url = "https://hebeae.com/calendar"
response = requests.get(url)

with open("c:/Users/mgran/Local Sites/culturacsi/app/public/calendar_html.txt", "w", encoding="utf-8") as f:
    f.write(response.text)

print(f"Downloaded HTML! Status {response.status_code}")

# Let's extract the entry-content rows
soup = BeautifulSoup(response.text, 'html.parser')
entry = soup.select_one('.entry-content')

if entry:
    children = entry.find_all(recursive=False)
    for i, child in enumerate(children):
        classes = child.get("class", [])
        print(f"[{i}] tag: {child.name}, classes: {classes}")
        if "kb-row-layout-wrap" in classes:
            bg_styles = child.find_all(style=re.compile("background"))
            print("    -> has elements with background styles inside:", len(bg_styles))
            
            # Print the whole tag (truncated)
            tag_str = str(child)[:300]
            print("    -> Head preview:", tag_str)
else:
    print("Could not find .entry-content")

