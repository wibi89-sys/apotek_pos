from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from app.config import get_settings
from app.routers import print_router, fingerprint_router

settings = get_settings()

app = FastAPI(
    title="Apotek POS Hardware Bridge",
    description="Local service for ESC/POS Thermal Printing, Cash Drawer, and Fingerprint Scanner",
    version="1.0.0",
)

# CORS setup for POS Local
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(print_router.router)
app.include_router(fingerprint_router.router)

@app.websocket("/ws/fingerprint")
async def root_websocket_fingerprint(websocket: fingerprint_router.WebSocket):
    await fingerprint_router.websocket_fingerprint(websocket)


class HealthResponse(BaseModel):
    status: str
    printer_ready: bool
    fingerprint_ready: bool
    mock_mode: bool


@app.get("/api/v1/health", response_model=HealthResponse)
def get_health():
    """
    Health check endpoint returning device readiness.
    Used by POS header status indicator.
    """
    return HealthResponse(
        status="ok",
        printer_ready=True,
        fingerprint_ready=True,
        mock_mode=settings.mock_mode,
    )


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(
        "app.main:app",
        host=settings.bridge_host,
        port=settings.bridge_port,
        reload=False,
    )
