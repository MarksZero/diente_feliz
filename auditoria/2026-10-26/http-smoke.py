"""Read/login checks against the local demo; no patient data is modified."""
import http.cookiejar
import json
import re
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

root = Path(__file__).resolve().parents[2]
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
base = 'http://127.0.0.1:8000'
results = {}

def request(path, values=None):
    data = None if values is None else urllib.parse.urlencode(values).encode()
    try:
        response = client.open(urllib.request.Request(base + path, data=data), timeout=20)
    except urllib.error.HTTPError as error:
        response = error
    return response, response.read().decode('utf-8', errors='replace')

response, html = request('/login')
results['login_page'] = response.status
token = re.search(r'name="_token" value="([^"]+)"', html).group(1)
results['csrf_missing'] = request('/login', {'email':'admin@dientefeliz.test','password':'incorrect'})[0].status
password = next(line.split('=',1)[1] for line in (root/'diente-feliz/.env').read_text().splitlines() if line.startswith('DEMO_PASSWORD='))
response, html = request('/login', {'_token':token,'email':'admin@dientefeliz.test','password':password})
results['authenticated_dashboard'] = response.status == 200 and '/logout' in html
results['security_headers'] = {key: response.headers.get(key) for key in ['X-Content-Type-Options','X-Frame-Options','Content-Security-Policy']}
results['cookies'] = []
for cookie in jar:
    attributes = {key.lower(): value for key, value in cookie._rest.items()}
    results['cookies'].append({'name':cookie.name,'secure':cookie.secure,'http_only':'httponly' in attributes,'same_site':attributes.get('samesite')})
for path in ['/modules/patients', '/modules/patients/1?view=advanced', '/reports', '/help', '/clinic.css']:
    results[path] = request(path)[0].status
response, html = request('/')
token = re.search(r'name="_token" value="([^"]+)"', html).group(1)
response, html = request('/logout', {'_token':token})
results['logout_returns_login'] = response.status == 200 and '/login' in response.url
target = Path(__file__).with_name('http-smoke.json')
target.write_text(json.dumps(results, indent=2, ensure_ascii=False), encoding='utf-8')
print(json.dumps(results, indent=2, ensure_ascii=False))
