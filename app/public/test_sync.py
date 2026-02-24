import requests
import json
import random

session = requests.Session()
login_url = 'https://hebeae.com/wp-login.php'
session.get(login_url)

# Do we need to login to read the raw PHP file? No, but we will login just to be sure we can drop the file? We can't drop a file from python via WP admin easily unless we use the theme editor or plugin editor!
# Wait! In previous steps, I dropped `diagnose.php` locally into `c:/.../public/diagnose.php` because the Local syncs with the remote? NO! The Local folder does NOT sync with the live server.
# Oh my god. 
