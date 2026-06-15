# API Documentation

## Authentication

All API endpoints require an active PHP session (cookie-based). The user must be logged in to access any endpoint. Requests must originate from the web application or have a valid session cookie.

---

## Endpoints

### GET `/dashboard/api/get_alerts.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `count`   | flag | Returns only open alert count |
| `id`      | int  | Fetch single alert by ID |
| `limit`   | int  | Number of alerts (default: 20, max: 100) |
| `severity`| string | Filter by severity level |
| `status`  | string | Filter by status |
| `format`  | string | `feed` for live attack feed format |
| `export`  | string | `csv` to download CSV |

**Response:**
```json
{
    "alerts": [...],
    "count": 12
}
```

---

### GET `/dashboard/api/get_logs.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `limit`   | int  | Max log entries (default: 50) |
| `type`    | string | Log type filter |
| `search`  | string | Search in message field |
| `export`  | flag | Download CSV |

---

### POST `/dashboard/api/ai_analysis.php`

**Request Body (JSON):**

| Field | Type | Description |
|-------|------|-------------|
| `type` | string | `quick`, `alert`, `custom`, `full_report`, `save` |
| `prompt` | string | User query (for custom/quick types) |
| `alert_id` | int | Alert ID to analyze (for alert type) |
| `title` | string | Report title (for save type) |
| `content` | string | Report content (for save type) |

**Response:**
```json
{
    "success": true,
    "analysis": "Gemini AI response text..."
}
```

**GET** `/dashboard/api/ai_analysis.php?id=<id>` — Fetch saved report

**DELETE** `/dashboard/api/ai_analysis.php` — Delete report (body: `{"id": 1}`)

---

### POST `/dashboard/api/block_ip.php`

**Block IP (POST):**
```json
{
    "ip": "1.2.3.4",
    "reason": "SSH brute force"
}
```

**Unblock IP (DELETE):**
```json
{
    "ip": "1.2.3.4"
}
```

**List blocked IPs (GET):** Returns all active blocks.

---

### GET `/dashboard/api/stats.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `type` | string | `overview`, `trends`, `top_ips` |
| `days` | int | Days for trend data (default: 7) |

**Trends Response:**
```json
{
    "series": [
        { "name": "Critical", "data": [3, 5, 2, 8, 4, 6, 3] },
        { "name": "High", "data": [8, 12, 7, 15, 9, 11, 8] }
    ],
    "categories": ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"],
    "severity": { "values": [5, 12, 24, 18, 8] }
}
```

---

## Error Responses

All endpoints return JSON errors in this format:

```json
{
    "success": false,
    "message": "Error description"
}
```

Common HTTP status codes:
- `200` — Success
- `403` — Unauthorized (not logged in, wrong role, invalid CSRF)
- `404` — Resource not found
- `405` — Method not allowed
- `500` — Server error
