import pytest
from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)


def test_print_test_receipt():
    response = client.post("/api/v1/print/test", json={"paper_width": 58})
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "success"
    assert data["drawer_opened"] is True
    assert "raw_text" in data
    assert "APOTEK POS HYBRID" in data["raw_text"]


def test_print_custom_receipt():
    payload = {
        "paper_width": 58,
        "open_drawer": True,
        "header": {
            "pharmacy_name": "Apotek Kimia Farmasi",
            "address": "Jl. Sehat No. 10",
        },
        "transaction": {
            "invoice_no": "INV-TEST-9999",
            "date": "22/11/2026 12:00:00",
            "cashier_name": "Budi",
            "type": "OTC",
        },
        "items": [
            {
                "name": "Paracetamol 500mg",
                "qty": "2",
                "unit": "Strip",
                "price": "10.000",
                "subtotal": "20.000",
            }
        ],
        "totals": {
            "subtotal": "20.000",
            "grand_total": "20.000",
            "paid_amount": "50.000",
            "change_amount": "30.000",
        },
        "footer": "Semoga Lekas Sembuh!",
    }
    response = client.post("/api/v1/print/receipt", json=payload)
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "success"
    assert "Paracetamol 500mg" in data["raw_text"]
    assert "30.000" in data["raw_text"]


def test_open_cash_drawer():
    response = client.post("/api/v1/drawer/open")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "success"
    assert data["action"] == "open_cash_drawer"
    assert data["pulse_sent"] is True
