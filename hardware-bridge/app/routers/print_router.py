from typing import Any, Dict
from fastapi import APIRouter, Body, Header, HTTPException, status
from app.config import get_settings
from app.services.printer import PrinterService

router = APIRouter(prefix="/api/v1", tags=["Printing & Hardware"])
printer_service = PrinterService()
settings = get_settings()


def verify_token(x_bridge_token: str = Header(None)):
    # Optional token verification if configured
    if settings.bridge_token and x_bridge_token:
        if x_bridge_token != settings.bridge_token:
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid X-Bridge-Token")
    return True


@router.post("/print/receipt")
def print_receipt(payload: Dict[str, Any] = Body(...)):
    try:
        result = printer_service.print_receipt(payload)
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/print/test")
def print_test(paper_width: int = Body(58, embed=True)):
    try:
        result = printer_service.print_test(paper_width=paper_width)
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/drawer/open")
def open_drawer():
    try:
        return printer_service.open_drawer()
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
