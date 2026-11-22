from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)


def test_fingerprint_status():
    response = client.get("/api/v1/fingerprint/status")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "ready"
    assert data["connected"] is True
    assert "device" in data


def test_fingerprint_enroll():
    response = client.post("/api/v1/fingerprint/enroll")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "success"
    assert "template" in data
    assert data["template"].startswith("DP_UAREU_ISO19794_2_")
    assert data["quality_score"] >= 90


def test_fingerprint_verify_matched():
    # Enroll first to get sample
    enroll_resp = client.post("/api/v1/fingerprint/enroll")
    template = enroll_resp.json()["template"]

    # Verify against same template
    verify_resp = client.post(
        "/api/v1/fingerprint/verify",
        json={"candidate_template": template, "enrolled_template": template},
    )
    assert verify_resp.status_code == 200
    data = verify_resp.json()
    assert data["matched"] is True
    assert data["confidence"] > 90.0


def test_fingerprint_verify_unmatched():
    verify_resp = client.post(
        "/api/v1/fingerprint/verify",
        json={
            "candidate_template": "INVALID_CORRUPTED_TEMPLATE",
            "enrolled_template": "OTHER_TEMPLATE_XYZ",
        },
    )
    assert verify_resp.status_code == 200
    data = verify_resp.json()
    assert data["matched"] is False


def test_fingerprint_websocket():
    with client.websocket_connect("/ws/fingerprint") as websocket:
        websocket.send_text("ping")
        data = websocket.receive_json()
        assert data["event"] == "pong"

        websocket.send_text("scan")
        scan_data = websocket.receive_json()
        assert scan_data["event"] == "scanned"
        assert scan_data["data"]["status"] == "success"
