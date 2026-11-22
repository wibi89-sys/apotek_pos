import os
from functools import lru_cache
from typing import List
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    bridge_host: str = "127.0.0.1"
    bridge_port: int = 8765
    bridge_token: str = "bridge-secret-token"
    allowed_origins: str = "http://localhost:8001,http://127.0.0.1:8001,http://localhost,http://127.0.0.1"
    mock_mode: bool = True

    printer_driver: str = "mock"
    printer_name: str = "POS-58"
    printer_paper_width: int = 58

    fingerprint_driver: str = "mock"

    model_config = SettingsConfigDict(
        env_file=os.path.join(os.path.dirname(os.path.dirname(__file__)), ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    @property
    def cors_origins(self) -> List[str]:
        return [origin.strip() for origin in self.allowed_origins.split(",") if origin.strip()]


@lru_cache
def get_settings() -> Settings:
    return Settings()
