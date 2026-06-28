from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
import hashlib
import random
import re
import requests
import json

app = FastAPI()

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

PAYMENT_API_URL = 'https://bayarin.cekstore.com/api/payment'
API_ID = '803b8a4ce56d8e5d'
API_KEY = 'aa5a04721c1e1d4a83ff57ecf3ea7eca5081d873004f2f51a8d798de6edc9e07'
EMAIL_REGEX = r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'

@app.post("/api/process")
async def process_payment(request: Request):
    try:
        body = await request.json()
    except:
        return JSONResponse(status_code=400, content={'success': False, 'msg': 'JSON tidak valid'})

    bank_code = body.get('bank_code', '').strip()
    amount = body.get('amount', '').strip()
    customer_name = body.get('customer_name', '').strip()
    customer_email = body.get('customer_email', '').strip()
    customer_phone = body.get('customer_phone', '').strip()
    payment_guide = body.get('payment_guide', 'false').strip()
    item_details = body.get('item_details', '').strip()

    if not all([bank_code, amount, customer_name, customer_email, customer_phone, item_details]):
        return JSONResponse(status_code=422, content={'success': False, 'msg': 'Field wajib diisi'})

    if not amount.isdigit() or int(amount) < 1:
        return JSONResponse(status_code=422, content={'success': False, 'msg': 'Amount tidak valid'})

    if not re.match(EMAIL_REGEX, customer_email):
        return JSONResponse(status_code=422, content={'success': False, 'msg': 'Email tidak valid'})

    reference_id = str(random.randint(100000000, 999999999))
    signature = hashlib.md5(f"{API_ID}{API_KEY}{reference_id}".encode()).hexdigest()

    payload = {
        'api_id': API_ID,
        'api_key': API_KEY,
        'signature': signature,
        'reference_id': reference_id,
        'bank_code': bank_code,
        'amount': amount,
        'customer_name': customer_name,
        'customer_email': customer_email,
        'customer_phone': customer_phone,
        'payment_guide': payment_guide,
        'item_details': item_details,
    }

    try:
        response = requests.post(PAYMENT_API_URL, json=payload, timeout=30)
    except Exception as e:
        return JSONResponse(status_code=400, content={'success': False, 'msg': f'Gagal konek: {str(e)}'})

    try:
        result = response.json()
    except:
        return JSONResponse(status_code=400, content={'success': False, 'msg': 'Response invalid'})

    status = 400 if response.status_code >= 500 else response.status_code
    return JSONResponse(content=result, status_code=status)

@app.get("/")
async def root():
    return {"message": "API Berjalan", "status": "OK"}

@app.get("/api/process")
async def get_not_allowed():
    return JSONResponse(status_code=405, content={"detail": "Method Not Allowed"})
