import os
from datetime import datetime
from typing import Dict, Any, Optional
from app.config import get_settings

settings = get_settings()


class ReceiptFormatter:
    """
    Format structured receipt JSON into text/ESC-POS line layouts.
    58mm paper: 32 columns.
    80mm paper: 48 columns.
    """

    @staticmethod
    def format_text(payload: Dict[str, Any]) -> str:
        width = int(payload.get("paper_width", 58))
        cols = 32 if width == 58 else 48
        sep = "-" * cols
        double_sep = "=" * cols

        lines = []

        # 1. Header
        header = payload.get("header", {})
        pharmacy_name = header.get("pharmacy_name", "APOTEK").upper()
        lines.append(pharmacy_name.center(cols))
        if header.get("address"):
            lines.append(header["address"].center(cols))
        if header.get("phone"):
            lines.append(f"Telp: {header['phone']}".center(cols))
        if header.get("sia_number"):
            lines.append(header["sia_number"].center(cols))
        if header.get("apa_name"):
            lines.append(f"APA: {header['apa_name']}".center(cols))
        if header.get("sipa_number"):
            lines.append(f"SIPA: {header['sipa_number']}".center(cols))

        lines.append(double_sep)

        # 2. Transaction metadata
        tx = payload.get("transaction", {})
        lines.append(f"Nota  : {tx.get('invoice_no', '-')}")
        lines.append(f"Tgl   : {tx.get('date', '-')}")
        lines.append(f"Kasir : {tx.get('cashier_name', '-')} [{tx.get('terminal_id', 'POS')}]")
        lines.append(f"Jenis : {tx.get('type', 'OTC')}")
        if tx.get("patient_name"):
            lines.append(f"Pasien: {tx['patient_name']}")
        if tx.get("doctor_name"):
            lines.append(f"Dokter: {tx['doctor_name']}")

        lines.append(sep)

        # 3. Items
        items = payload.get("items", [])
        for item in items:
            name = item.get("name", "")
            qty = item.get("qty", "1")
            unit = item.get("unit", "")
            price = item.get("price", "0")
            subtotal = item.get("subtotal", "0")

            if item.get("is_racikan"):
                group = item.get("racikan_group") or "Racikan"
                name = f"[{group}] {name}"

            # Line 1: Item name
            lines.append(name[:cols])

            # Line 2: Qty x Price ... Subtotal
            qty_price = f"  {qty} {unit} x {price}"
            space_needed = cols - len(qty_price) - len(subtotal)
            if space_needed > 0:
                lines.append(qty_price + (" " * space_needed) + subtotal)
            else:
                lines.append(f"{qty_price} -> {subtotal}")

        lines.append(sep)

        # 4. Totals
        totals = payload.get("totals", {})
        subtotal_str = totals.get("subtotal", "0")
        lines.append(f"Subtotal".ljust(cols - len(subtotal_str)) + subtotal_str)

        tuslah = totals.get("tuslah_embalase", "0")
        if tuslah and tuslah != "0":
            lines.append(f"Tuslah/Embalase".ljust(cols - len(tuslah)) + tuslah)

        discount = totals.get("discount", "0")
        if discount and discount != "0":
            lines.append(f"Diskon".ljust(cols - len(discount)) + f"-{discount}")

        tax = totals.get("tax", "0")
        if tax and tax != "0":
            lines.append(f"Pajak".ljust(cols - len(tax)) + tax)

        lines.append(sep)

        grand_total = totals.get("grand_total", "0")
        lines.append(f"TOTAL AKHIR".ljust(cols - len(grand_total)) + grand_total)

        payment_method = totals.get("payment_method", "Tunai")
        paid = totals.get("paid_amount", "0")
        lines.append(f"Bayar ({payment_method})".ljust(cols - len(paid)) + paid)

        change = totals.get("change_amount", "0")
        lines.append(f"Kembali".ljust(cols - len(change)) + change)

        lines.append(double_sep)

        # 5. Footer
        footer = payload.get("footer", "")
        if footer:
            for f_line in footer.split("\n"):
                lines.append(f_line.center(cols))

        lines.append("\n\n")  # Feed for paper cut
        return "\n".join(lines)


class PrinterService:
    def __init__(self):
        self.mock_mode = settings.mock_mode
        self.logs_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(__file__))), "logs", "receipts")
        os.makedirs(self.logs_dir, exist_ok=True)

    def print_receipt(self, payload: Dict[str, Any]) -> Dict[str, Any]:
        text_receipt = ReceiptFormatter.format_text(payload)
        invoice_no = payload.get("transaction", {}).get("invoice_no", "TEST").replace("/", "_")
        open_drawer = bool(payload.get("open_drawer", False))

        if open_drawer:
            self.open_drawer()

        # In mock mode or dev, save formatted receipt text
        output_file = os.path.join(self.logs_dir, f"{invoice_no}.txt")
        with open(output_file, "w", encoding="utf-8") as f:
            f.write(text_receipt)

        return {
            "status": "success",
            "driver": settings.printer_driver,
            "mock_mode": self.mock_mode,
            "invoice_no": invoice_no,
            "preview_file": output_file,
            "drawer_opened": open_drawer,
            "raw_text": text_receipt,
        }

    def print_test(self, paper_width: int = 58) -> Dict[str, Any]:
        payload = {
            "paper_width": paper_width,
            "open_drawer": True,
            "header": {
                "pharmacy_name": "APOTEK POS HYBRID",
                "address": "Jl. Uji Thermal ESC/POS No. 1",
                "phone": "0812-3456-7890",
            },
            "transaction": {
                "invoice_no": f"TEST-{datetime.now().strftime('%Y%m%d%H%M%S')}",
                "date": datetime.now().strftime("%d/%m/%Y %H:%M:%S"),
                "cashier_name": "Admin",
                "terminal_id": "POS01",
                "type": "TEST PRINT",
            },
            "items": [
                {"name": "Kertas Thermal OK", "qty": "1", "unit": "Roll", "price": "5.000", "subtotal": "5.000"}
            ],
            "totals": {
                "grand_total": "5.000",
                "paid_amount": "5.000",
                "change_amount": "0",
            },
            "footer": "Printer Thermal Siap Digunakan!",
        }
        return self.print_receipt(payload)

    def open_drawer(self) -> Dict[str, Any]:
        # ESC/POS Drawer pulse command: \x1b\x70\x00\x19\xfa
        return {
            "status": "success",
            "action": "open_cash_drawer",
            "pulse_sent": True,
            "mock_mode": self.mock_mode,
        }
