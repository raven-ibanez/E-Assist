"""
E-Assist Enrollment System — Diagram Generator
Generates 7 academic-style diagrams as black & white PNG images.
Font: Times New Roman, 12pt equivalent.
"""

from PIL import Image, ImageDraw, ImageFont
import os, math

# ── Configuration ──────────────────────────────────────────────
OUTPUT_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "diagrams")
os.makedirs(OUTPUT_DIR, exist_ok=True)

# Font setup — Times New Roman on Windows
FONT_PATH = r"C:\Windows\Fonts\times.ttf"
FONT_BOLD_PATH = r"C:\Windows\Fonts\timesbd.ttf"
FONT_ITALIC_PATH = r"C:\Windows\Fonts\timesi.ttf"

# At 150 DPI, 12pt ≈ 25px. We use 20px for body, 26px for headings.
FONT_SM = ImageFont.truetype(FONT_PATH, 18)
FONT_BODY = ImageFont.truetype(FONT_PATH, 20)
FONT_BOLD = ImageFont.truetype(FONT_BOLD_PATH, 22)
FONT_TITLE = ImageFont.truetype(FONT_BOLD_PATH, 28)
FONT_HEADING = ImageFont.truetype(FONT_BOLD_PATH, 24)
FONT_TINY = ImageFont.truetype(FONT_PATH, 15)
FONT_LABEL = ImageFont.truetype(FONT_ITALIC_PATH, 16)

BLACK = "black"
WHITE = "white"
GRAY = "#888888"
LIGHT_GRAY = "#E0E0E0"
LINE_W = 2


# ── Helper Functions ───────────────────────────────────────────

def text_size(draw, text, font):
    bbox = draw.textbbox((0, 0), text, font=font)
    return bbox[2] - bbox[0], bbox[3] - bbox[1]


def draw_text_centered(draw, cx, cy, text, font=FONT_BODY, fill=BLACK):
    w, h = text_size(draw, text, font)
    draw.text((cx - w / 2, cy - h / 2), text, font=font, fill=fill)


def draw_text_left(draw, x, y, text, font=FONT_BODY, fill=BLACK):
    draw.text((x, y), text, font=font, fill=fill)


def draw_rect(draw, x, y, w, h, text="", font=FONT_BODY, fill=WHITE, outline=BLACK, lw=LINE_W, multi=False):
    draw.rectangle([x, y, x + w, y + h], fill=fill, outline=outline, width=lw)
    if text and not multi:
        draw_text_centered(draw, x + w / 2, y + h / 2, text, font)
    elif text and multi:
        lines = text.split("\n")
        total_h = len(lines) * 22
        start_y = y + (h - total_h) / 2
        for i, line in enumerate(lines):
            draw_text_centered(draw, x + w / 2, start_y + i * 22 + 10, line, font)


def draw_rounded_rect(draw, x, y, w, h, radius=15, text="", font=FONT_BODY, fill=WHITE, outline=BLACK, lw=LINE_W):
    draw.rounded_rectangle([x, y, x + w, y + h], radius=radius, fill=fill, outline=outline, width=lw)
    if text:
        draw_text_centered(draw, x + w / 2, y + h / 2, text, font)


def draw_ellipse(draw, cx, cy, rx, ry, text="", font=FONT_BODY, fill=WHITE, outline=BLACK, lw=LINE_W):
    draw.ellipse([cx - rx, cy - ry, cx + rx, cy + ry], fill=fill, outline=outline, width=lw)
    if text:
        draw_text_centered(draw, cx, cy, text, font)


def draw_diamond(draw, cx, cy, half_w, half_h, text="", font=FONT_SM, fill=WHITE, outline=BLACK, lw=LINE_W):
    pts = [(cx, cy - half_h), (cx + half_w, cy), (cx, cy + half_h), (cx - half_w, cy)]
    draw.polygon(pts, fill=fill, outline=outline, width=lw)
    if text:
        lines = text.split("\n")
        total = len(lines) * 20
        sy = cy - total / 2
        for i, ln in enumerate(lines):
            draw_text_centered(draw, cx, sy + i * 20 + 10, ln, font)


def draw_arrow(draw, x1, y1, x2, y2, lw=LINE_W, fill=BLACK, head_size=10):
    draw.line([(x1, y1), (x2, y2)], fill=fill, width=lw)
    angle = math.atan2(y2 - y1, x2 - x1)
    lx = x2 - head_size * math.cos(angle - math.pi / 6)
    ly = y2 - head_size * math.sin(angle - math.pi / 6)
    rx = x2 - head_size * math.cos(angle + math.pi / 6)
    ry = y2 - head_size * math.sin(angle + math.pi / 6)
    draw.polygon([(x2, y2), (lx, ly), (rx, ry)], fill=fill)


def draw_arrow_label(draw, x1, y1, x2, y2, label="", font=FONT_TINY, lw=LINE_W, fill=BLACK):
    draw_arrow(draw, x1, y1, x2, y2, lw=lw, fill=fill)
    if label:
        mx, my = (x1 + x2) / 2, (y1 + y2) / 2
        draw_flow_label(draw, label, mx, my, font)


def draw_flow_label(draw, label, cx, cy, font=FONT_TINY):
    if not label:
        return
    lines = label.split("\n")
    tw = 0
    line_h = 18
    for line in lines:
        w, _ = text_size(draw, line, font)
        tw = max(tw, w)
    th = len(lines) * line_h
    draw.rectangle([cx - tw / 2 - 4, cy - th / 2 - 2, cx + tw / 2 + 4, cy + th / 2 + 2], fill=WHITE)
    start_y = cy - th / 2
    for i, line in enumerate(lines):
        draw_text_centered(draw, cx, start_y + i * line_h + line_h / 2, line, font)


def draw_line(draw, x1, y1, x2, y2, lw=LINE_W, fill=BLACK):
    draw.line([(x1, y1), (x2, y2)], fill=fill, width=lw)


def draw_dashed_line(draw, x1, y1, x2, y2, dash_len=8, gap=5, lw=LINE_W, fill=BLACK):
    dx = x2 - x1
    dy = y2 - y1
    dist = math.sqrt(dx * dx + dy * dy)
    if dist == 0:
        return
    ux, uy = dx / dist, dy / dist
    pos = 0
    while pos < dist:
        sx = x1 + ux * pos
        sy = y1 + uy * pos
        end = min(pos + dash_len, dist)
        ex = x1 + ux * end
        ey = y1 + uy * end
        draw.line([(sx, sy), (ex, ey)], fill=fill, width=lw)
        pos = end + gap


def add_title(draw, W, title, y=25, font=FONT_TITLE):
    tw, th = text_size(draw, title, font)
    draw_text_centered(draw, W / 2, y + th / 2, title, font)
    sub_y = y + th + 8
    draw.line([(W / 2 - tw / 2 - 20, sub_y), (W / 2 + tw / 2 + 20, sub_y)], fill=BLACK, width=2)
    sub_y2 = sub_y + 6
    sub = "E-Assist Enrollment System"
    stw, sth = text_size(draw, sub, FONT_LABEL)
    draw_text_centered(draw, W / 2, sub_y2 + sth / 2, sub, FONT_LABEL)
    return sub_y2 + sth + 15


# ══════════════════════════════════════════════════════════════
#  1. SYSTEM FLOWCHART
# ══════════════════════════════════════════════════════════════
def gen_system_flowchart():
    W, H = 900, 1750
    img = Image.new("RGB", (W, H), WHITE)
    d = ImageDraw.Draw(img)

    y_start = add_title(d, W, "Figure 1: System Flowchart")
    cx = W // 2
    bw, bh = 260, 45
    dw, dh = 110, 55
    y = y_start + 10

    # Start terminal
    draw_rounded_rect(d, cx - 70, y, 140, 40, 20, "Start", FONT_BOLD)
    y += 40
    ny = y + 35
    draw_arrow(d, cx, y, cx, ny)
    y = ny

    steps = [
        "Parent Visits Homepage",
        "Fill Student Information\n(Step 1)",
        "Fill Parent/Guardian\nInformation (Step 2)",
        "Upload Documents\n(Step 3)",
        "Select Payment Method\n& Mode (Step 4)",
        "Submit Enrollment\nApplication",
        "System Saves Data\nto Database",
        "Confirmation Email\nSent to Parent",
    ]

    for i, step in enumerate(steps):
        lines = step.count("\n") + 1
        h = 40 + (lines - 1) * 18
        draw_rect(d, cx - bw // 2, y, bw, h, step, FONT_BODY, multi=True)
        y += h
        ny = y + 30
        draw_arrow(d, cx, y, cx, ny)
        y = ny

    # Decision 1: Registrar reviews
    draw_diamond(d, cx, y + dh, dw + 20, dh, "Registrar\nApproved?", FONT_SM)
    dy_top = y
    y += 2 * dh

    # Yes path
    ny = y + 30
    draw_arrow(d, cx, y, cx, ny)
    tw, th = text_size(d, "Yes", FONT_TINY)
    d.text((cx + 5, y + 5), "Yes", font=FONT_TINY, fill=BLACK)
    y = ny

    # No path (right)
    no_x = cx + dw + 20
    draw_arrow(d, no_x, dy_top + dh, no_x + 80, dy_top + dh)
    d.text((no_x + 20, dy_top + dh - 20), "No", font=FONT_TINY, fill=BLACK)
    # Declined box on the right
    draw_rect(d, no_x + 80, dy_top + dh - 22, 180, 45, "Application\nDeclined", FONT_BODY, multi=True)
    # Arrow down from declined
    dec_end_y = dy_top + dh + 23
    draw_arrow(d, no_x + 170, dec_end_y, no_x + 170, dec_end_y + 40)
    draw_rect(d, no_x + 80, dec_end_y + 40, 180, 40, "Email Sent to Parent", FONT_SM)
    draw_arrow(d, no_x + 170, dec_end_y + 80, no_x + 170, dec_end_y + 120)
    draw_rounded_rect(d, no_x + 105, dec_end_y + 120, 130, 35, 17, "End", FONT_BOLD)

    # Cashier reviews
    draw_rect(d, cx - bw // 2, y, bw, 45, "Cashier Reviews Payment", FONT_BODY)
    y += 45
    ny = y + 30
    draw_arrow(d, cx, y, cx, ny)
    y = ny

    # Decision 2: Cashier approved?
    draw_diamond(d, cx, y + dh, dw + 20, dh, "Cashier\nApproved?", FONT_SM)
    dy2_top = y
    y += 2 * dh

    # Yes path
    ny = y + 30
    draw_arrow(d, cx, y, cx, ny)
    d.text((cx + 5, y + 5), "Yes", font=FONT_TINY, fill=BLACK)
    y = ny

    # No path (right) for cashier
    no_x2 = cx + dw + 20
    draw_arrow(d, no_x2, dy2_top + dh, no_x2 + 80, dy2_top + dh)
    d.text((no_x2 + 20, dy2_top + dh - 20), "No", font=FONT_TINY, fill=BLACK)
    draw_rect(d, no_x2 + 80, dy2_top + dh - 22, 180, 45, "Payment\nDeclined", FONT_BODY, multi=True)
    dec2_end_y = dy2_top + dh + 23
    draw_arrow(d, no_x2 + 170, dec2_end_y, no_x2 + 170, dec2_end_y + 40)
    draw_rect(d, no_x2 + 80, dec2_end_y + 40, 180, 40, "Email Sent to Parent", FONT_SM)
    draw_arrow(d, no_x2 + 170, dec2_end_y + 80, no_x2 + 170, dec2_end_y + 120)
    draw_rounded_rect(d, no_x2 + 105, dec2_end_y + 120, 130, 35, 17, "End", FONT_BOLD)

    # Enrolled
    draw_rect(d, cx - bw // 2, y, bw, 45, "Student Enrolled", FONT_BOLD)
    y += 45
    ny = y + 30
    draw_arrow(d, cx, y, cx, ny)
    y = ny

    draw_rect(d, cx - bw // 2, y, bw, 40, "Email Sent to Parent", FONT_BODY)
    y += 40
    ny = y + 30
    draw_arrow(d, cx, y, cx, ny)
    y = ny

    draw_rounded_rect(d, cx - 70, y, 140, 40, 20, "End", FONT_BOLD)

    img.save(os.path.join(OUTPUT_DIR, "1_system_flowchart.png"), dpi=(150, 150))
    print("  [OK] 1_system_flowchart.png")


# ══════════════════════════════════════════════════════════════
#  2. DATA FLOW DIAGRAM — LEVEL 0 (Context Diagram)
# ══════════════════════════════════════════════════════════════
def gen_dfd_level0():
    W, H = 1100, 850
    img = Image.new("RGB", (W, H), WHITE)
    d = ImageDraw.Draw(img)

    y_start = add_title(d, W, "Figure 2: Data Flow Diagram — Level 0")

    # Central process (circle)
    pcx, pcy = W // 2, y_start + 230
    pr = 90
    draw_ellipse(d, pcx, pcy, pr, pr, "E-Assist\nEnrollment\nSystem", FONT_BODY, outline=BLACK)

    # External entities (rectangles)
    entities = [
        {"name": "Parent /\nGuardian", "x": 60, "y": y_start + 50, "w": 150, "h": 60},
        {"name": "Registrar", "x": W - 210, "y": y_start + 50, "w": 150, "h": 50},
        {"name": "Cashier", "x": W - 210, "y": y_start + 350, "w": 150, "h": 50},
        {"name": "Admin", "x": 60, "y": y_start + 350, "w": 150, "h": 50},
        {"name": "Email\nService", "x": W // 2 - 75, "y": y_start + 470, "w": 150, "h": 55},
    ]

    for ent in entities:
        draw_rect(d, ent["x"], ent["y"], ent["w"], ent["h"], ent["name"], FONT_BODY, multi=True)

    # Data flows (arrows with labels)
    # Parent → System
    draw_arrow(d, 210, y_start + 65, pcx - pr + 10, pcy - 50)
    draw_flow_label(d, "Enrollment Data,\nDocuments, Payment Info", 295, y_start + 90)

    # System → Parent
    draw_arrow(d, pcx - pr, pcy + 10, 210, y_start + 95)
    draw_flow_label(d, "Confirmation,\nReceipt, Status Email", 370, y_start + 180)

    # System → Registrar
    draw_arrow(d, pcx + pr - 10, pcy - 50, W - 210, y_start + 65)
    draw_flow_label(d, "Application Data,\nStudent Details", 805, y_start + 90)

    # Registrar → System
    draw_arrow(d, W - 210, y_start + 85, pcx + pr, pcy - 10)
    draw_flow_label(d, "Approval / Decline\nDecision", 730, y_start + 180)

    # System → Cashier
    draw_arrow(d, pcx + pr - 10, pcy + 30, W - 210, y_start + 360)
    draw_flow_label(d, "Payment Data,\nFee Details", 805, y_start + 300)

    # Cashier → System
    draw_arrow(d, W - 210, y_start + 390, pcx + pr, pcy + 60)
    draw_flow_label(d, "Payment Approval,\nTransaction Records", 730, y_start + 390)

    # Admin → System
    draw_arrow(d, 210, y_start + 365, pcx - pr + 10, pcy + 30)
    draw_flow_label(d, "System Config,\nEmployee Mgmt", 295, y_start + 300)

    # System → Admin
    draw_arrow(d, pcx - pr, pcy + 60, 210, y_start + 390)
    draw_flow_label(d, "Reports, Logs,\nSystem Data", 370, y_start + 390)

    # System → Email Service
    draw_arrow_label(d, pcx, pcy + pr, pcx, y_start + 470,
                     "Email Notifications", FONT_TINY)

    img.save(os.path.join(OUTPUT_DIR, "2_dfd_level_0.png"), dpi=(150, 150))
    print("  [OK] 2_dfd_level_0.png")


# ══════════════════════════════════════════════════════════════
#  3. DATA FLOW DIAGRAM — LEVEL 1
# ══════════════════════════════════════════════════════════════
def gen_dfd_level1():
    W, H = 1100, 1000
    img = Image.new("RGB", (W, H), WHITE)
    d = ImageDraw.Draw(img)

    y_start = add_title(d, W, "Figure 3: Data Flow Diagram — Level 1")

    # Left Column: External entities
    # Parent/Student entity box
    px, py = 80, y_start + 280
    pw, ph = 120, 120
    draw_rect(d, px, py, pw, ph, "Student /\nParent", FONT_BODY, multi=True)

    # Middle column: Processes (circles)
    p1cx, p1cy = 380, y_start + 340
    p1r = 75
    draw_ellipse(d, p1cx, p1cy, p1r, p1r, "1.0\nEnrollment\nSubmission", FONT_SM)

    p2cx, p2cy = 620, y_start + 120
    p2r = 75
    draw_ellipse(d, p2cx, p2cy, p2r, p2r, "2.0\nRegistrar\nReview", FONT_SM)

    p3cx, p3cy = 620, y_start + 440
    p3r = 75
    draw_ellipse(d, p3cx, p3cy, p3r, p3r, "3.0\nCashier\nPayment\nReview", FONT_SM)

    p4cx, p4cy = 620, y_start + 720
    p4r = 75
    draw_ellipse(d, p4cx, p4cy, p4r, p4r, "4.0\nSystem\nAdministration", FONT_SM)

    # Far-Right Column: External entities
    reg_x, reg_y = 850, y_start + 50
    draw_rect(d, reg_x, reg_y, 120, 120, "Registrar", FONT_BODY)

    cash_x, cash_y = 850, y_start + 360
    draw_rect(d, cash_x, cash_y, 120, 120, "Cashier", FONT_BODY)

    admin_x, admin_y = 850, y_start + 650
    draw_rect(d, admin_x, admin_y, 120, 120, "Admin", FONT_BODY)

    # Data stores (open-ended rectangles)
    def draw_data_store(d, x, y, w, h, label, font=FONT_SM):
        draw.line([(x, y), (x + w, y)], fill=BLACK, width=LINE_W)
        draw.line([(x, y + h), (x + w, y + h)], fill=BLACK, width=LINE_W)
        draw.line([(x, y), (x, y + h)], fill=BLACK, width=LINE_W)
        # vertical separator
        draw.line([(x + 30, y), (x + 30, y + h)], fill=BLACK, width=LINE_W)
        draw_text_centered(d, x + 15, y + h / 2, label.split(":")[0].strip(), FONT_TINY)
        draw_text_centered(d, x + 30 + (w - 30) / 2, y + h / 2, label.split(":")[1].strip(), FONT_SM)

    draw = d  # alias for draw_data_store closure
    
    # Data Stores
    draw_data_store(d, 280, y_start + 530, 200, 30, "D1 : Student/Parent DB")
    draw_data_store(d, 520, y_start + 270, 200, 30, "D2 : Enrollment DB")
    draw_data_store(d, 520, y_start + 590, 200, 30, "D3 : Payments DB")
    draw_data_store(d, 520, y_start + 850, 200, 30, "D4 : System Logs DB")

    # ── Arrows and flows ──

    # Parent → 1.0 (Top arrow)
    draw_arrow(d, px + pw, py + 40, p1cx - p1r, py + 40)
    draw_flow_label(d, "Enrollment Form,\nDocument", (px + pw + p1cx - p1r) // 2, py + 15)

    # 1.0 → Parent (Bottom arrow)
    draw_arrow(d, p1cx - p1r, py + 80, px + pw, py + 80)
    draw_flow_label(d, "Receipts,\nStatus Updates", (px + pw + p1cx - p1r) // 2, py + 105)

    # 1.0 → D1
    draw_arrow(d, p1cx, p1cy + p1r, p1cx, y_start + 530)
    draw_text_centered(d, p1cx - 45, y_start + 472, "Read D1", FONT_TINY)
    draw_text_centered(d, p1cx + 45, y_start + 472, "Write D1", FONT_TINY)

    # Branching arrow from 1.0 to 2.0, 3.0, 4.0
    # Line goes right to x=500
    draw_line(d, p1cx + p1r, p1cy, 500, p1cy)
    # Vertical line from y=y_start+120 to y=y_start+720
    draw_line(d, 500, y_start + 120, 500, y_start + 720)
    # Horizontal arrows pointing into circles
    draw_arrow(d, 500, y_start + 120, p2cx - p2r, y_start + 120)
    draw_arrow(d, 500, y_start + 440, p3cx - p3r, y_start + 440)
    draw_arrow(d, 500, y_start + 720, p4cx - p4r, y_start + 720)

    # 2.0 → D2
    draw_arrow(d, p2cx, p2cy + p2r, p2cx, y_start + 270)
    draw_text_centered(d, p2cx - 50, y_start + 232, "Read D2", FONT_TINY)
    draw_text_centered(d, p2cx + 50, y_start + 232, "Write D1", FONT_TINY)

    # D3 → 3.0
    draw_arrow(d, p3cx, y_start + 590, p3cx, p3cy + p3r)
    draw_text_centered(d, p3cx + 45, y_start + 550, "Write D3", FONT_TINY)

    # D4 → 4.0
    draw_arrow(d, p4cx, y_start + 850, p4cx, p4cy + p4r)

    # 4.0 → D1
    draw_line(d, p4cx - p4r, p4cy, 380, p4cy)
    draw_arrow(d, 380, p4cy, 380, y_start + 560)

    # 2.0 to/from Registrar
    # Top arrow: 2.0 → Registrar
    draw_arrow(d, p2cx + p2r, p2cy - 20, reg_x, p2cy - 20)
    draw_flow_label(d, "Applicant Info,\nDocument Status", (p2cx + p2r + reg_x) // 2, p2cy - 45)
    # Bottom arrow: Registrar → 2.0
    draw_arrow(d, reg_x, p2cy + 20, p2cx + p2r, p2cy + 20)
    draw_flow_label(d, "Review Decisions\n(Approve/Reject)", (p2cx + p2r + reg_x) // 2, p2cy + 45)

    # 3.0 to/from Cashier
    # Top arrow: 3.0 → Cashier
    draw_arrow(d, p3cx + p3r, p3cy - 20, cash_x, p3cy - 20)
    draw_flow_label(d, "Financial\nSummaries,\nBalance", (p3cx + p3r + cash_x) // 2, p3cy - 60)
    # Bottom arrow: Cashier → 3.0
    draw_arrow(d, cash_x, p3cy + 20, p3cx + p3r, p3cy + 20)
    draw_flow_label(d, "Payment Validation,\nRefunds", (p3cx + p3r + cash_x) // 2, p3cy + 45)

    # 4.0 to/from Admin
    # Top arrow: 4.0 → Admin
    draw_arrow(d, p4cx + p4r, p4cy - 20, admin_x, p4cy - 20)
    draw_flow_label(d, "Audit Logs,\nSystem Overview", (p4cx + p4r + admin_x) // 2, p4cy - 45)
    # Bottom arrow: Admin → 4.0
    draw_arrow(d, admin_x, p4cy + 20, p4cx + p4r, p4cy + 20)
    draw_flow_label(d, "System\nConfiguration", (p4cx + p4r + admin_x) // 2, p4cy + 45)

    img.save(os.path.join(OUTPUT_DIR, "3_dfd_level_1.png"), dpi=(150, 150))
    print("  [OK] 3_dfd_level_1.png")


# ══════════════════════════════════════════════════════════════
#  4. USE CASE DIAGRAM
# ══════════════════════════════════════════════════════════════
def gen_use_case_diagram():
    W, H = 1200, 1050
    img = Image.new("RGB", (W, H), WHITE)
    d = ImageDraw.Draw(img)

    y_start = add_title(d, W, "Figure 4: Use Case Diagram")

    # System boundary
    bx, by, bw, bh = 250, y_start + 10, 700, 850
    d.rectangle([bx, by, bx + bw, by + bh], outline=BLACK, width=LINE_W)
    draw_text_centered(d, bx + bw / 2, by + 18, "E-Assist Enrollment System", FONT_BOLD)

    # Stick figure helper
    def draw_actor(d, cx, y, label):
        # Head
        d.ellipse([cx - 10, y, cx + 10, y + 20], outline=BLACK, width=LINE_W)
        # Body
        d.line([(cx, y + 20), (cx, y + 50)], fill=BLACK, width=LINE_W)
        # Arms
        d.line([(cx - 18, y + 32), (cx + 18, y + 32)], fill=BLACK, width=LINE_W)
        # Legs
        d.line([(cx, y + 50), (cx - 15, y + 70)], fill=BLACK, width=LINE_W)
        d.line([(cx, y + 50), (cx + 15, y + 70)], fill=BLACK, width=LINE_W)
        # Label
        draw_text_centered(d, cx, y + 82, label, FONT_SM)

    # Actors
    parent_cx, parent_y = 100, y_start + 120
    draw_actor(d, parent_cx, parent_y, "Parent/Guardian")

    reg_cx, reg_y = W - 100, y_start + 80
    draw_actor(d, reg_cx, reg_y, "Registrar")

    cash_cx, cash_y = W - 100, y_start + 380
    draw_actor(d, cash_cx, cash_y, "Cashier")

    admin_cx, admin_y = W - 100, y_start + 620
    draw_actor(d, admin_cx, admin_y, "Admin")

    # Use cases (ellipses inside boundary)
    uc_x = bx + bw / 2
    use_cases_left = [
        ("Fill Student\nInformation", uc_x - 150, y_start + 100),
        ("Fill Parent\nInformation", uc_x - 150, y_start + 190),
        ("Upload\nDocuments", uc_x - 150, y_start + 280),
        ("Select Payment\nMethod & Mode", uc_x - 150, y_start + 370),
        ("Submit Enrollment\nApplication", uc_x - 150, y_start + 460),
        ("View Enrollment\nReceipt", uc_x - 150, y_start + 550),
        ("Receive Email\nNotification", uc_x - 150, y_start + 640),
    ]

    use_cases_right = [
        ("Review\nApplication", uc_x + 150, y_start + 120),
        ("Approve / Decline\nApplication", uc_x + 150, y_start + 210),
        ("Review Payment\nDetails", uc_x + 150, y_start + 360),
        ("Approve / Decline\nPayment", uc_x + 150, y_start + 450),
        ("Record Payment\nTransaction", uc_x + 150, y_start + 540),
        ("Manage Employee\nAccounts", uc_x + 150, y_start + 660),
        ("View System\nLogs & Reports", uc_x + 150, y_start + 750),
        ("Configure Payment\nModes & Fees", uc_x + 150, y_start + 840),
    ]

    erx, ery = 110, 35

    for label, ex, ey in use_cases_left:
        draw_ellipse(d, ex, ey, erx, ery, label, FONT_TINY, outline=BLACK)
        # Line from parent actor to use case
        draw_line(d, parent_cx + 20, min(max(parent_y + 35, ey), parent_y + 60), ex - erx, ey)

    for label, ex, ey in use_cases_right:
        draw_ellipse(d, ex, ey, erx, ery, label, FONT_TINY, outline=BLACK)

    # Connect registrar to review/approve
    draw_line(d, reg_cx - 20, reg_y + 35, use_cases_right[0][1] + erx, use_cases_right[0][2])
    draw_line(d, reg_cx - 20, reg_y + 45, use_cases_right[1][1] + erx, use_cases_right[1][2])

    # Connect cashier to payment use cases
    draw_line(d, cash_cx - 20, cash_y + 25, use_cases_right[2][1] + erx, use_cases_right[2][2])
    draw_line(d, cash_cx - 20, cash_y + 35, use_cases_right[3][1] + erx, use_cases_right[3][2])
    draw_line(d, cash_cx - 20, cash_y + 50, use_cases_right[4][1] + erx, use_cases_right[4][2])

    # Connect admin to manage/logs/config
    draw_line(d, admin_cx - 20, admin_y + 30, use_cases_right[5][1] + erx, use_cases_right[5][2])
    draw_line(d, admin_cx - 20, admin_y + 40, use_cases_right[6][1] + erx, use_cases_right[6][2])
    draw_line(d, admin_cx - 20, admin_y + 55, use_cases_right[7][1] + erx, use_cases_right[7][2])

    img.save(os.path.join(OUTPUT_DIR, "4_use_case_diagram.png"), dpi=(150, 150))
    print("  [OK] 4_use_case_diagram.png")


# ══════════════════════════════════════════════════════════════
#  5. ACTIVITY DIAGRAM
# ══════════════════════════════════════════════════════════════
def gen_activity_diagram():
    W, H = 1450, 1750
    img = Image.new("RGB", (W, H), WHITE)
    d = ImageDraw.Draw(img)

    y_start = add_title(d, W, "Figure 5: Activity Diagram")

    # Swimlanes
    lanes = ["System Administrator", "Parent/Guardian", "System", "Registrar", "Cashier"]
    lane_w = (W - 40) // len(lanes)
    lane_x = 20

    for i, name in enumerate(lanes):
        x = lane_x + i * lane_w
        d.rectangle([x, y_start, x + lane_w, H - 40], outline=BLACK, width=LINE_W)
        draw_text_centered(d, x + lane_w / 2, y_start + 18, name, FONT_BOLD)
        d.line([(x, y_start + 35), (x + lane_w, y_start + 35)], fill=BLACK, width=LINE_W)

    def lcx(lane_idx):
        return lane_x + lane_idx * lane_w + lane_w / 2

    rw, rh = 210, 40
    y = y_start + 55

    # ── 1. Start Node in System Administrator (Lane 0) ──
    d.ellipse([lcx(0) - 12, y, lcx(0) + 12, y + 24], fill=BLACK)
    y += 24
    ny = y + 20
    draw_arrow(d, lcx(0), y, lcx(0), ny)
    y = ny

    # Admin: Setup configuration
    draw_rounded_rect(d, lcx(0) - rw / 2, y, rw, rh + 16, 12, "Configure Payment\nModes & Fees", FONT_SM)
    y += rh + 16
    ny = y + 20
    draw_arrow(d, lcx(0), y, lcx(0), ny)
    y = ny

    draw_rounded_rect(d, lcx(0) - rw / 2, y, rw, rh + 16, 12, "Set Up Employee\nAccounts", FONT_SM)
    y += rh + 16
    
    # Transition to Parent/Guardian (Lane 1) - Edge-to-Edge
    draw_arrow(d, lcx(0) + rw / 2, y - rh - 8, lcx(1) - rw / 2, y + 10)
    y += 30

    # ── 2. Parent Activities (Lane 1) ──
    activities = [
        "Visit Homepage",
        "Fill Student Info\n(Step 1)",
        "Fill Parent Info\n(Step 2)",
        "Upload Documents\n(Step 3)",
        "Select Payment\n(Step 4)",
        "Submit Application",
    ]

    for label in activities:
        lines = label.count("\n") + 1
        h = rh + (lines - 1) * 16
        draw_rounded_rect(d, lcx(1) - rw / 2, y, rw, h, 12, label, FONT_SM)
        y += h
        ny = y + 25
        if label != "Submit Application":
            draw_arrow(d, lcx(1), y, lcx(1), ny)
        else:
            # Transition to System (Lane 2) - Edge-to-Edge
            draw_arrow(d, lcx(1) + rw / 2, y - h // 2, lcx(2) - rw / 2, y - h // 2)
        y = ny

    # ── 3. System Activities (Lane 2) ──
    sys_activities = [
        "Validate & Save\nData to Database",
        "Generate Student\nNumber",
        "Send Confirmation\nEmail",
    ]

    # Align y-coordinate for System start
    y = y - 25
    for label in sys_activities:
        lines = label.count("\n") + 1
        h = rh + (lines - 1) * 16
        draw_rounded_rect(d, lcx(2) - rw / 2, y, rw, h, 12, label, FONT_SM)
        y += h
        ny = y + 25
        if label != "Send Confirmation\nEmail":
            draw_arrow(d, lcx(2), y, lcx(2), ny)
        else:
            # Transition to Registrar (Lane 3) - Edge-to-Edge
            draw_arrow(d, lcx(2) + rw / 2, y - h // 2, lcx(3) - rw / 2, y - h // 2)
        y = ny

    # ── 4. Registrar Review & Decision (Lane 3) ──
    y = y - 25
    draw_rounded_rect(d, lcx(3) - rw / 2, y, rw, rh + 16, 12, "Review\nApplication", FONT_SM)
    y += rh + 16
    ny = y + 20
    draw_arrow(d, lcx(3), y, lcx(3), ny)
    y = ny

    # Decision diamond
    draw_diamond(d, lcx(3), y + 30, 60, 30, "Approve?", FONT_TINY)
    dec_y = y
    y += 60

    # Registrar No Path → System Send Rejection Email (Edge-to-Edge)
    draw_arrow(d, lcx(3) - 60, dec_y + 30, lcx(2) + rw / 2, dec_y + 30)
    d.text((lcx(2) + rw / 2 + 10, dec_y + 10), "No", font=FONT_TINY, fill=BLACK)
    draw_rounded_rect(d, lcx(2) - rw / 2, dec_y + 5, rw, rh + 16, 12, "Send Registrar\nRejection Email", FONT_SM)
    # Registrar Rejection End Node in Lane 2
    draw_arrow(d, lcx(2), dec_y + rh + 21, lcx(2), dec_y + rh + 45)
    d.ellipse([lcx(2) - 15, dec_y + rh + 45, lcx(2) + 15, dec_y + rh + 75], outline=BLACK, width=LINE_W)
    d.ellipse([lcx(2) - 9, dec_y + rh + 51, lcx(2) + 9, dec_y + rh + 69], fill=BLACK)

    # Registrar Yes Path → Cashier Review
    ny = y + 20
    draw_arrow(d, lcx(3), y, lcx(3), ny)
    d.text((lcx(3) + 5, y + 2), "Yes", font=FONT_TINY, fill=BLACK)
    y = ny
    draw_arrow(d, lcx(3), y, lcx(4) - rw / 2, y)

    # ── 5. Cashier Review & Decision (Lane 4) ──
    draw_rounded_rect(d, lcx(4) - rw / 2, y, rw, rh + 16, 12, "Review\nPayment", FONT_SM)
    y += rh + 16
    ny = y + 20
    draw_arrow(d, lcx(4), y, lcx(4), ny)
    y = ny

    # Decision diamond
    draw_diamond(d, lcx(4), y + 30, 60, 30, "Approve?", FONT_TINY)
    dec2_y = y
    y += 60

    # Cashier No Path → Route to Lane 1 (Parent/Guardian) to prevent vertical overlaps in Lane 2
    draw_arrow(d, lcx(4) - 60, dec2_y + 30, lcx(1) + rw / 2, dec2_y + 30)
    d.text((lcx(1) + rw / 2 + 20, dec2_y + 10), "No", font=FONT_TINY, fill=BLACK)
    draw_rounded_rect(d, lcx(1) - rw / 2, dec2_y + 5, rw, rh + 16, 12, "Send Payment\nRejection Email", FONT_SM)
    # Cashier Rejection End Node in Lane 1
    draw_arrow(d, lcx(1), dec2_y + rh + 21, lcx(1), dec2_y + rh + 45)
    d.ellipse([lcx(1) - 15, dec2_y + rh + 45, lcx(1) + 15, dec2_y + rh + 75], outline=BLACK, width=LINE_W)
    d.ellipse([lcx(1) - 9, dec2_y + rh + 51, lcx(1) + 9, dec2_y + rh + 69], fill=BLACK)

    # Cashier Yes Path → System Update Status
    ny = y + 20
    draw_arrow(d, lcx(4), y, lcx(4), ny)
    d.text((lcx(4) + 5, y + 2), "Yes", font=FONT_TINY, fill=BLACK)
    y = ny
    draw_arrow(d, lcx(4), y, lcx(2) + rw / 2, y)

    # ── 6. System Finalizing Enrollment (Lane 2) ──
    draw_rounded_rect(d, lcx(2) - rw / 2, y, rw, rh + 16, 12, "Update Status:\nEnrolled", FONT_SM)
    y += rh + 16
    ny = y + 20
    draw_arrow(d, lcx(2), y, lcx(2), ny)
    y = ny

    draw_rounded_rect(d, lcx(2) - rw / 2, y, rw, rh, 12, "Send Status Email", FONT_SM)
    y += rh
    ny = y + 25
    
    # Transition to System Administrator for Monitoring (Lane 0) - Edge-to-Edge
    draw_arrow(d, lcx(2) - rw / 2, y, lcx(0) + rw / 2, y + 30)
    y = ny + 30

    # ── 7. Admin Monitoring & End (Lane 0) ──
    draw_rounded_rect(d, lcx(0) - rw / 2, y, rw, rh + 16, 12, "Monitor System Logs\n& Generate Reports", FONT_SM)
    y += rh + 16
    ny = y + 25
    draw_arrow(d, lcx(0), y, lcx(0), ny)
    y = ny

    # Final node (bullseye) in Admin Lane
    d.ellipse([lcx(0) - 15, y, lcx(0) + 15, y + 30], outline=BLACK, width=LINE_W)
    d.ellipse([lcx(0) - 9, y + 6, lcx(0) + 9, y + 24], fill=BLACK)

    img.save(os.path.join(OUTPUT_DIR, "5_activity_diagram.png"), dpi=(150, 150))
    print("  [OK] 5_activity_diagram.png")


# ══════════════════════════════════════════════════════════════
#  6. SEQUENCE DIAGRAM
# ══════════════════════════════════════════════════════════════
def gen_sequence_diagram():
    W, H = 1300, 1350
    img = Image.new("RGB", (W, H), WHITE)
    d = ImageDraw.Draw(img)

    y_start = add_title(d, W, "Figure 6: Sequence Diagram")

    # Lifelines
    lifelines = [
        ("Parent /\nGuardian", 100),
        ("Browser\n(Frontend)", 280),
        ("API Server\n(PHP)", 480),
        ("Database\n(MySQL)", 680),
        ("Email\nService", 860),
        ("Registrar", 1020),
        ("Cashier", 1180),
    ]

    ll_top = y_start + 20
    ll_box_h = 45
    ll_bottom = H - 60

    for label, x in lifelines:
        draw_rect(d, x - 55, ll_top, 110, ll_box_h, label, FONT_SM, multi=True)
        draw_dashed_line(d, x, ll_top + ll_box_h, x, ll_bottom, 6, 4, 1)

    y = ll_top + ll_box_h + 30

    def msg(d, from_x, to_x, y, label, dashed=False, self_call=False, font=FONT_TINY):
        if self_call:
            d.line([(from_x, y), (from_x + 40, y)], fill=BLACK, width=LINE_W)
            d.line([(from_x + 40, y), (from_x + 40, y + 20)], fill=BLACK, width=LINE_W)
            draw_arrow(d, from_x + 40, y + 20, from_x, y + 20, head_size=7)
            tw, th = text_size(d, label, font)
            d.text((from_x + 45, y - 2), label, font=font, fill=BLACK)
            return y + 30
        if dashed:
            draw_dashed_line(d, from_x, y, to_x, y, 6, 4, LINE_W)
            # arrowhead
            if to_x < from_x:
                head_size = 7
                d.polygon([(to_x, y), (to_x + head_size, y - head_size / 2), (to_x + head_size, y + head_size / 2)], fill=BLACK)
            else:
                head_size = 7
                d.polygon([(to_x, y), (to_x - head_size, y - head_size / 2), (to_x - head_size, y + head_size / 2)], fill=BLACK)
        else:
            draw_arrow(d, from_x, y, to_x, y, head_size=7)
        mx = (from_x + to_x) / 2
        tw, th = text_size(d, label, font)
        d.rectangle([mx - tw / 2 - 2, y - th - 4, mx + tw / 2 + 2, y - 2], fill=WHITE)
        d.text((mx - tw / 2, y - th - 3), label, font=font, fill=BLACK)
        return y + 35

    parent, browser, api, db, email, reg, cashier_x = [x for _, x in lifelines]

    # 1. Parent → Browser: Open enrollment page
    y = msg(d, parent, browser, y, "1. Open enrollment page")
    # 2. Browser → API: GET lookups (grade levels, etc.)
    y = msg(d, browser, api, y, "2. GET /api/lookups.php")
    # 3. API → DB: Query lookup tables
    y = msg(d, api, db, y, "3. SELECT grade_levels, relations...")
    # 4. DB → API: Return lookup data
    y = msg(d, db, api, y, "4. Return lookup data", dashed=True)
    # 5. API → Browser: JSON response
    y = msg(d, api, browser, y, "5. JSON lookup data", dashed=True)
    # 6. Parent → Browser: Fill forms (Steps 1-4)
    y = msg(d, parent, browser, y, "6. Fill enrollment form (Steps 1-4)")
    # 7. Browser → API: POST enrollment data + files
    y = msg(d, browser, api, y, "7. POST /api/register.php (FormData)")
    # 8. API → DB: Begin transaction
    y = msg(d, api, db, y, "8. BEGIN TRANSACTION")
    # 9. API → DB: INSERT parent, student, enrollment, payment
    y = msg(d, api, db, y, "9. INSERT parent, student, enrollment, payment")
    # 10. DB → API: Return IDs
    y = msg(d, db, api, y, "10. COMMIT — return IDs", dashed=True)
    # 11. API → Email: Send confirmation
    y = msg(d, api, email, y, "11. Send confirmation email")
    # 12. API → Browser: Success + student_no
    y = msg(d, api, browser, y, "12. JSON { success, student_no }", dashed=True)
    # 13. Browser → Parent: Show receipt
    y = msg(d, browser, parent, y, "13. Display enrollment receipt", dashed=True)

    # Divider
    d.line([(30, y), (W - 30, y)], fill=GRAY, width=1)
    draw_text_centered(d, W / 2, y + 10, "— Review Phase —", FONT_LABEL)
    y += 30

    # 14. Registrar → Browser: Login
    y = msg(d, reg, browser, y, "14. Login (username, password)")
    y = msg(d, browser, api, y, "15. POST /api/registrar.php?action=login")
    y = msg(d, api, browser, y, "16. { role: registrar }", dashed=True)
    # 17. Registrar → Browser: View applications
    y = msg(d, reg, browser, y, "17. GET ?action=students")
    y = msg(d, browser, api, y, "18. Forward request")
    y = msg(d, api, db, y, "19. SELECT enrollments + status")
    y = msg(d, db, api, y, "20. Return enrollment list", dashed=True)
    y = msg(d, api, browser, y, "21. JSON application list", dashed=True)
    # 22. Registrar → API: Approve
    y = msg(d, reg, api, y, "22. POST ?action=review_application (approved)")
    y = msg(d, api, db, y, "23. INSERT enrollment_reviews")
    y = msg(d, api, email, y, "24. Send approval email to parent")

    # Divider
    d.line([(30, y), (W - 30, y)], fill=GRAY, width=1)
    y += 15

    # Cashier reviews
    y = msg(d, cashier_x, api, y, "25. POST ?action=review_payment (approved)")
    y = msg(d, api, db, y, "26. INSERT enrollment_reviews (Cashier)")
    y = msg(d, api, email, y, "27. Send 'Enrolled' email to parent")

    img.save(os.path.join(OUTPUT_DIR, "6_sequence_diagram.png"), dpi=(150, 150))
    print("  [OK] 6_sequence_diagram.png")


# ══════════════════════════════════════════════════════════════
#  7. SYSTEM ARCHITECTURE DIAGRAM
# ══════════════════════════════════════════════════════════════
def gen_architecture_diagram():
    W, H = 1250, 950
    img = Image.new("RGB", (W, H), WHITE)
    d = ImageDraw.Draw(img)

    y_start = add_title(d, W, "Figure 7: System Architecture Diagram")

    cx = W // 2
    layer_w = 900
    lx = cx - layer_w // 2

    y = y_start + 20

    # ── Layer 1: Client / Presentation Layer ──
    layer_h = 200
    d.rectangle([lx, y, lx + layer_w, y + layer_h], outline=BLACK, width=LINE_W)
    draw_text_centered(d, cx, y + 18, "Presentation Layer (Client-Side)", FONT_BOLD)
    d.line([(lx, y + 35), (lx + layer_w, y + 35)], fill=BLACK, width=1)

    # Sub-boxes
    boxes = [
        ("index.html\n(Homepage)", lx + 30, y + 45, 150, 75),
        ("enroll-*.html\n(Enrollment\nSteps 1-4)", lx + 218, y + 45, 150, 95),
        ("success.html\n(Receipt)", lx + 406, y + 45, 130, 75),
        ("employee-\nlogin.html", lx + 574, y + 45, 120, 75),
        ("*-dashboard\n.html\n(Admin, Registrar,\nCashier)", lx + 732, y + 45, 140, 100),
    ]
    for label, bx, by, bw, bh in boxes:
        draw_rect(d, bx, by, bw, bh, label, FONT_TINY, multi=True)

    # Shared resources
    draw_rect(d, lx + 150, y + 155, 250, 35, "style.css", FONT_SM)
    draw_rect(d, lx + 500, y + 155, 250, 35, "main.js (API helpers)", FONT_SM)

    y += layer_h

    # Arrow between layers
    arr_y = y + 15
    draw_arrow(d, cx - 30, arr_y - 5, cx - 30, arr_y + 25)
    draw_arrow(d, cx + 30, arr_y + 25, cx + 30, arr_y - 5)
    draw_text_centered(d, cx + 100, arr_y + 10, "HTTP / AJAX (JSON, FormData)", FONT_TINY)
    y = arr_y + 35

    # ── Layer 2: Application / API Layer ──
    layer_h = 210
    d.rectangle([lx, y, lx + layer_w, y + layer_h], outline=BLACK, width=LINE_W)
    draw_text_centered(d, cx, y + 18, "Application Layer (PHP Backend — XAMPP/Apache)", FONT_BOLD)
    d.line([(lx, y + 35), (lx + layer_w, y + 35)], fill=BLACK, width=1)

    api_boxes = [
        ("register.php\n(Enrollment\nSubmission)", lx + 30, y + 45, 150, 90),
        ("registrar.php\n(Login, CRUD,\nReviews)", lx + 218, y + 45, 150, 90),
        ("lookups.php\n(Dropdown\nData)", lx + 406, y + 45, 130, 90),
        ("reports.php\n(Analytics)", lx + 574, y + 45, 120, 75),
        ("maintenance.php\n(System\nConfig)", lx + 732, y + 45, 140, 90),
    ]
    for label, bx, by, bw, bh in api_boxes:
        draw_rect(d, bx, by, bw, bh, label, FONT_TINY, multi=True)

    draw_rect(d, lx + 50, y + 155, 220, 45, "db.php\n(DB Connection)", FONT_SM, multi=True)
    draw_rect(d, lx + 310, y + 155, 280, 45, "email_config.php\n(PHPMailer Templates)", FONT_SM, multi=True)
    draw_rect(d, lx + 630, y + 155, 220, 45, "send_email.php", FONT_SM)

    y += layer_h

    # Arrow between layers
    arr_y = y + 15
    draw_arrow(d, cx - 80, arr_y - 5, cx - 80, arr_y + 25)
    draw_arrow(d, cx - 40, arr_y + 25, cx - 40, arr_y - 5)
    draw_text_centered(d, cx - 60, arr_y + 10, "MySQLi Queries", FONT_TINY)

    # Side arrow to email
    email_bx = lx + layer_w + 30
    draw_arrow(d, lx + layer_w, y - 50, email_bx, y - 50)
    draw_rect(d, email_bx, y - 75, 110, 55, "SMTP\nEmail Server\n(Gmail)", FONT_SM, multi=True)

    y = arr_y + 35

    # ── Layer 3: Data Layer ──
    layer_h = 230
    d.rectangle([lx, y, lx + layer_w, y + layer_h], outline=BLACK, width=LINE_W)
    draw_text_centered(d, cx, y + 18, "Data Layer (MySQL — enrollment_db)", FONT_BOLD)
    d.line([(lx, y + 35), (lx + layer_w, y + 35)], fill=BLACK, width=1)

    # Database tables as a grid
    tables_row1 = ["students", "parents", "enrollments", "payments"]
    tables_row2 = ["payment_transactions", "enrollment_reviews", "admin", "roles"]
    tables_row3 = ["school_years", "grade_levels", "sessions", "system_logs"]
    tables_row4 = ["payment_methods", "payment_modes", "form_fields", "enrollment_field_values"]

    def draw_table_row(d, row, y, lx, layer_w):
        cols = len(row)
        tw = (layer_w - 40) // cols
        for i, name in enumerate(row):
            tx = lx + 20 + i * tw
            draw_rect(d, tx, y, tw - 10, 32, name, FONT_TINY)

    draw_table_row(d, tables_row1, y + 45, lx, layer_w)
    draw_table_row(d, tables_row2, y + 90, lx, layer_w)
    draw_table_row(d, tables_row3, y + 135, lx, layer_w)
    draw_table_row(d, tables_row4, y + 180, lx, layer_w)

    # File storage
    fs_y = y + layer_h + 15
    draw_rect(d, lx + layer_w // 2 - 150, fs_y, 300, 45, "File Storage: /api/uploads/\n(PSA, SF10, 2x2 Photos)", FONT_SM, multi=True)

    img.save(os.path.join(OUTPUT_DIR, "7_system_architecture.png"), dpi=(150, 150))
    print("  [OK] 7_system_architecture.png")


# ══════════════════════════════════════════════════════════════
#  MAIN
# ══════════════════════════════════════════════════════════════
if __name__ == "__main__":
    print(f"\nGenerating diagrams in: {OUTPUT_DIR}\n")
    gen_system_flowchart()
    gen_dfd_level0()
    gen_dfd_level1()
    gen_use_case_diagram()
    gen_activity_diagram()
    gen_sequence_diagram()
    gen_architecture_diagram()
    print(f"\n[DONE] All 7 diagrams generated successfully in '{OUTPUT_DIR}'.\n")
