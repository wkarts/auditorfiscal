from __future__ import annotations

from decimal import Decimal
from pathlib import Path

from reportlab.graphics.barcode.code128 import Code128
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import KeepTogether, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle


BLACK = colors.HexColor("#17212B")
GRAY = colors.HexColor("#E9EDF1")
GRID = colors.HexColor("#7D8994")


def _money(value) -> str:
    return f"{Decimal(str(value or 0)):,.2f}".replace(",", "X").replace(".", ",").replace("X", ".")


def _text(value) -> str:
    return str(value) if value not in (None, "") else "—"


def _address(party: dict) -> str:
    address = party.get("address") or {}
    line = ", ".join(filter(None, [address.get("street"), address.get("number"), address.get("district")]))
    city = " - ".join(filter(None, [address.get("city"), address.get("state")]))
    return " · ".join(filter(None, [line, city, address.get("postal_code")])) or "—"


def build_danfe(path: Path, document: dict) -> None:
    """Gera representação auxiliar para consulta; o XML permanece como documento fiscal fonte."""
    normalized = document.get("normalized") or {}
    issuer = normalized.get("issuer") or {}
    recipient = normalized.get("recipient") or {}
    protocol = normalized.get("protocol") or {}
    totals = normalized.get("totals") or {}
    access_key = document.get("access_key") or "SEM CHAVE DE ACESSO"
    styles = getSampleStyleSheet()
    body = ParagraphStyle("danfe-body", parent=styles["BodyText"], fontName="Helvetica", fontSize=7, leading=8.5, textColor=BLACK)
    small = ParagraphStyle("danfe-small", parent=body, fontSize=6, leading=7)
    label = ParagraphStyle("danfe-label", parent=small, fontName="Helvetica-Bold", textColor=colors.HexColor("#425466"))
    title = ParagraphStyle("danfe-title", parent=body, fontName="Helvetica-Bold", fontSize=15, leading=17, alignment=TA_CENTER)
    right = ParagraphStyle("danfe-right", parent=body, alignment=TA_RIGHT)
    center = ParagraphStyle("danfe-center", parent=body, alignment=TA_CENTER)

    pdf = SimpleDocTemplate(
        str(path), pagesize=A4, leftMargin=7 * mm, rightMargin=7 * mm,
        topMargin=7 * mm, bottomMargin=9 * mm,
        title=f"DANFE NF-e {document.get('number') or ''}", author="Auditor Fiscal",
    )

    def box(rows, widths, repeat=0):
        table = Table(rows, colWidths=widths, repeatRows=repeat)
        table.setStyle(TableStyle([
            ("BOX", (0, 0), (-1, -1), .7, GRID),
            ("INNERGRID", (0, 0), (-1, -1), .25, GRID),
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("LEFTPADDING", (0, 0), (-1, -1), 3),
            ("RIGHTPADDING", (0, 0), (-1, -1), 3),
            ("TOPPADDING", (0, 0), (-1, -1), 2.5),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 2.5),
        ]))
        return table

    header_left = [
        Paragraph(f"<b>{_text(issuer.get('name') or normalized.get('issuer_name'))}</b>", body),
        Paragraph(_address(issuer), small),
        Paragraph(f"CNPJ: {_text(issuer.get('tax_id') or document.get('issuer_tax_id'))} · IE: {_text(issuer.get('state_registration'))}", small),
    ]
    header_center = [
        Paragraph("DANFE", title),
        Paragraph("Documento Auxiliar da Nota Fiscal Eletrônica", center),
        Paragraph(f"{'1 - SAÍDA' if document.get('direction') == 'saida' else '0 - ENTRADA'}", center),
    ]
    header_right = [
        Paragraph(f"<b>NF-e nº {_text(document.get('number'))}</b>", right),
        Paragraph(f"Série {_text(document.get('series'))}", right),
        Paragraph(f"Folha 1", right),
    ]
    story = [box([[header_left, header_center, header_right]], [87 * mm, 55 * mm, 55 * mm]), Spacer(1, 2 * mm)]

    barcode = Code128(str(access_key), barHeight=10 * mm, barWidth=.28 * mm) if str(access_key).isdigit() else Paragraph(_text(access_key), center)
    story += [box([
        [Paragraph("CHAVE DE ACESSO", label), Paragraph("PROTOCOLO DE AUTORIZAÇÃO", label)],
        [barcode, Paragraph(f"{_text(protocol.get('number'))}<br/>{_text(protocol.get('received_at'))}<br/>{_text(protocol.get('status_reason'))}", center)],
    ], [126 * mm, 71 * mm]), Spacer(1, 2 * mm)]

    identification = normalized.get("identification") or {}
    story += [box([
        [Paragraph("NATUREZA DA OPERAÇÃO", label), Paragraph("MODELO", label), Paragraph("EMISSÃO", label)],
        [Paragraph(_text(identification.get("nature") or normalized.get("nature")), body), Paragraph(_text(document.get("model")), center), Paragraph(_text(document.get("issued_at")), center)],
    ], [126 * mm, 22 * mm, 49 * mm]), Spacer(1, 2 * mm)]

    story += [Paragraph("DESTINATÁRIO / REMETENTE", label), box([
        [Paragraph("NOME / RAZÃO SOCIAL", label), Paragraph("CNPJ / CPF", label), Paragraph("INSCRIÇÃO ESTADUAL", label)],
        [Paragraph(_text(recipient.get("name") or normalized.get("recipient_name")), body), Paragraph(_text(recipient.get("tax_id") or document.get("recipient_tax_id")), body), Paragraph(_text(recipient.get("state_registration")), body)],
        [Paragraph("ENDEREÇO", label), Paragraph("MUNICÍPIO / UF", label), Paragraph("TELEFONE / E-MAIL", label)],
        [Paragraph(_address(recipient), body), Paragraph(_text((recipient.get("address") or {}).get("city")), body), Paragraph(" · ".join(filter(None, [recipient.get("phone"), recipient.get("email")])) or "—", body)],
    ], [91 * mm, 61 * mm, 45 * mm]), Spacer(1, 2 * mm)]

    story += [Paragraph("CÁLCULO DO IMPOSTO", label), box([
        [Paragraph(x, label) for x in ["BASE ICMS", "VALOR ICMS", "BASE ICMS ST", "VALOR ST", "TOTAL PRODUTOS", "VALOR DA NOTA"]],
        [Paragraph(_money(v), right) for v in [totals.get("vBC"), totals.get("vICMS"), totals.get("vBCST"), totals.get("vST"), totals.get("vProd"), document.get("total_value")]],
        [Paragraph(x, label) for x in ["FRETE", "SEGURO", "DESCONTO", "IPI", "PIS", "COFINS"]],
        [Paragraph(_money(v), right) for v in [totals.get("vFrete"), totals.get("vSeg"), totals.get("vDesc"), totals.get("vIPI"), totals.get("vPIS"), totals.get("vCOFINS")]],
    ], [32.83 * mm] * 6), Spacer(1, 2 * mm)]

    item_rows = [[Paragraph(x, label) for x in ["ITEM", "CÓDIGO / DESCRIÇÃO", "NCM", "CFOP", "UN.", "QTD.", "V. UNIT.", "V. TOTAL", "CST / CCLASS"]]]
    for item in document.get("items") or []:
        details = item.get("details") or {}
        unit_value = details.get("unit_value")
        if unit_value is None and details.get("quantity"):
            try:
                unit_value = Decimal(str(item.get("product_value") or 0)) / Decimal(str(details["quantity"]))
            except (ArithmeticError, ValueError):
                unit_value = None
        item_rows.append([
            Paragraph(_text(item.get("item_number")), center),
            Paragraph(f"{_text(item.get('product_code'))} · {_text(item.get('description'))}", small),
            Paragraph(_text(item.get("ncm")), center),
            Paragraph(_text(item.get("cfop")), center),
            Paragraph(_text(details.get("unit")), center),
            Paragraph(_text(details.get("quantity")), right),
            Paragraph(_money(unit_value), right),
            Paragraph(_money(item.get("product_value")), right),
            Paragraph(f"{_text(item.get('actual_cst'))} / {_text(item.get('actual_cclass_trib'))}", center),
        ])
    if len(item_rows) == 1:
        item_rows.append([Paragraph("Nenhum item disponível", center)] + [""] * 8)
    story += [Paragraph("DADOS DOS PRODUTOS / SERVIÇOS", label), box(item_rows, [10 * mm, 62 * mm, 18 * mm, 14 * mm, 10 * mm, 16 * mm, 20 * mm, 21 * mm, 26 * mm], repeat=1), Spacer(1, 2 * mm)]

    transport = normalized.get("transport") or {}
    billing = normalized.get("billing") or {}
    additional = normalized.get("additional_information") or {}
    story += [KeepTogether([
        Paragraph("TRANSPORTADOR / VOLUMES", label),
        box([[Paragraph(f"Modalidade do frete: {_text(transport.get('freight_mode'))}", body), Paragraph(f"Placa / UF: {_text(transport.get('vehicle_plate'))} / {_text(transport.get('vehicle_state'))}", body)]], [126 * mm, 71 * mm]),
        Spacer(1, 2 * mm),
        Paragraph("COBRANÇA E INFORMAÇÕES COMPLEMENTARES", label),
        box([[Paragraph(f"Fatura: {_text(billing.get('invoice_number'))} · Valor líquido: {_money(billing.get('net_value'))}", body)], [Paragraph(_text(additional.get('taxpayer') or additional.get('tax_authority')), small)]], [197 * mm]),
    ])]

    def footer(canvas, doc):
        canvas.saveState()
        canvas.setFont("Helvetica", 6.5)
        canvas.drawString(7 * mm, 5 * mm, "DANFE gerado pelo Auditor Fiscal a partir do XML autorizado. O XML é o documento fiscal fonte.")
        canvas.drawRightString(A4[0] - 7 * mm, 5 * mm, f"Página {doc.page}")
        canvas.restoreState()

    pdf.build(story, onFirstPage=footer, onLaterPages=footer)
