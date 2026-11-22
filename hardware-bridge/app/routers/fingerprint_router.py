from fastapi import APIRouter, WebSocket, WebSocketDisconnect, HTTPException
from pydantic import BaseModel
from typing import Optional, List
import asyncio
from app.config import get_settings
from app.services.fingerprint import get_fingerprint_driver

router = APIRouter(prefix="/api/v1/fingerprint", tags=["Fingerprint"])
settings = get_settings()
driver = get_fingerprint_driver(mock_mode=settings.mock_mode)


class EnrollResponse(BaseModel):
    status: str
    message: str
    template: str
    quality_score: int


class VerifyRequest(BaseModel):
    candidate_template: str
    enrolled_template: str


class VerifyResponse(BaseModel):
    matched: bool
    confidence: float
    message: str


@router.get("/status")
def get_fingerprint_status():
    """Check fingerprint scanner readiness and driver details."""
    return driver.get_status()


@router.post("/enroll", response_model=EnrollResponse)
def enroll_fingerprint():
    """Capture fingerprint sample from scanner and return template."""
    result = driver.enroll()
    if result["status"] != "success":
        raise HTTPException(status_code=400, detail=result.get("message", "Gagal memindai sidik jari"))
    return result


@router.post("/verify", response_model=VerifyResponse)
def verify_fingerprint(req: VerifyRequest):
    """Verify live/candidate template against stored enrolled template."""
    return driver.verify(req.candidate_template, req.enrolled_template)


# Active WebSocket clients connected for live fingerprint scan events
active_fp_connections: List[WebSocket] = []


@router.websocket("/ws/fingerprint")
async def websocket_fingerprint(websocket: WebSocket):
    """WebSocket endpoint for real-time cashier scan events."""
    await websocket.accept()
    active_fp_connections.append(websocket)
    try:
        while True:
            # Receive client ping or scan request
            data = await websocket.receive_text()
            if data == "scan":
                enroll_result = driver.enroll()
                await websocket.send_json({
                    "event": "scanned",
                    "data": enroll_result
                })
            else:
                await websocket.send_json({"event": "pong", "message": "Bridge fingerprint ready"})
    except WebSocketDisconnect:
        active_fp_connections.remove(websocket)
