import pytest
from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)


def test_health_check_returns_200_and_expected_fields():
    response = client.get("/api/v1/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "ok"
    assert "printer_ready" in data
    assert "fingerprint_ready" in data
    assert "mock_mode" in data
    assert isinstance(data["printer_ready"], bool)
    assert isinstance(data["fingerprint_ready"], bool)
    assert isinstance(data["mock_mode"], bool)
