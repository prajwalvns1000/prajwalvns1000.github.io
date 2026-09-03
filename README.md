# Prajwal N — Headless WordPress CMS Portfolio

This project converts Prajwal N's personal portfolio into a decoupled **Headless WordPress CMS** architecture as required for the training task.

The original custom frontend (HTML5, CSS3, ES6 JavaScript) is **100% preserved** (no redesign, no WordPress theme replacement), and dynamically consumes **Projects** and **Work Experience** from a local WordPress instance via the **WordPress REST API**.

---

## 🏛️ Architecture Overview

```
+-------------------------------------------------------------------------+
|                  WordPress CMS & Admin Dashboard                        |
|                  http://localhost:8000/wp-admin                         |
|                                                                         |
|   - Custom Post Type: "Projects" (slug: projects)                       |
|   - Custom Post Type: "Work Experience" (slug: experience)              |
|   - Custom Meta Boxes for Tech Stack, Tags, Links, Dates, etc.          |
+------------------------------------+------------------------------------+
                                     |
                                     | REST API (HTTP GET + CORS headers)
                                     v
+-------------------------------------------------------------------------+
|                        WordPress REST API                               |
|                                                                         |
|   - GET /wp-json/wp/v2/projects                                         |
|   - GET /wp-json/wp/v2/experience                                       |
+------------------------------------+------------------------------------+
                                     |
                                     | JSON Data
                                     v
+-------------------------------------------------------------------------+
|                    Decoupled Custom Portfolio Frontend                  |
|                    http://localhost:3000                                |
|                                                                         |
|   - js/config.js (WORDPRESS_API_URL configured)                         |
|   - js/script.js (dynamic fetch, DOM rendering & modal bindings)        |
|   - index.html (dynamic projects grid & experience timeline)            |
|   - css/style.css (original visual identity preserved)                  |
|   - Offline Fallback (smooth static display if WordPress is offline)    |
+-------------------------------------------------------------------------+
```

---

## 🚀 Quick Start (Local Setup)

Both the frontend and backend can run on your machine without requiring separate Apache or MySQL installations, powered by Node.js and WordPress Playground.

### Prerequisites
- Node.js installed (v18+)

### Step 1: Start the WordPress CMS Backend
Open a terminal in the project directory (`D:\profile`) and run:
```bash
npm run wordpress
```
- **WordPress Admin**: [http://localhost:8000/wp-admin](http://localhost:8000/wp-admin)
The `wordpress` command starts WordPress Playground without opening a browser login. Use `npm run wordpress:admin` when you need the admin dashboard and create or use the Playground account shown there.
- **REST API Endpoints**:
  - `http://localhost:8000/wp-json/wp/v2/projects`
  - `http://localhost:8000/wp-json/wp/v2/experience`

### Step 2: Start the Portfolio Frontend
In a second terminal window, run:
```bash
npm run frontend
```
- **Portfolio Frontend**: [http://localhost:3000](http://localhost:3000)

---

## 🎯 How to Demonstrate to Your Trainer

### Demo 1 — Add a New Project
1. Open [http://localhost:8000/wp-admin](http://localhost:8000/wp-admin) and log in (`admin` / `password`).
2. In the left sidebar, click **Projects** &rarr; **Add New**.
3. Fill in:
   - **Title**: `Smart IoT Gateway`
   - **Main Content Editor**: Project description, technical overview, and applications.
   - **Card Short Summary**: `High-performance edge gateway for sensor clusters.`
   - **Technologies Subtitle**: `ESP32 | MQTT | FreeRTOS`
   - **Tech Tags**: `ESP32, MQTT, FreeRTOS, RS485, WebSockets`
   - **Key Technical Concepts**:
     ```
     Multi-threaded sensor polling
     TLS encryption for telemetry
     ```
4. Click **Publish** (top right).
5. Open your portfolio at [http://localhost:3000](http://localhost:3000) (or refresh the page).
6. **Result**: Your new project immediately appears dynamically on the portfolio! Click **View Technical Details** to see the interactive modal populated with the CMS content.

---

### Demo 2 — Add a New Work Experience
1. In WordPress Admin, click **Work Experience** &rarr; **Add New**.
2. Fill in:
   - **Title**: `Research Intern at Indian Institute of Science`
   - **Main Content Editor**: Responsibilities and key contributions.
   - **Company / Organization Name**: `Indian Institute of Science (IISc)`
   - **Job / Internship Title**: `Research Intern`
   - **Start Date**: `January 2027`
   - **End Date**: `Present`
   - **Location**: `Bengaluru, Karnataka`
   - **Skills / Technologies**: `Python, Digital Signal Processing, Edge AI`
3. Click **Publish**.
4. Refresh your portfolio at [http://localhost:3000](http://localhost:3000).
5. **Result**: The new experience entry immediately appears in the Work Experience timeline!

---

## ⚙️ Configuration & Alternative Setups

### API Base URL (`js/config.js`)
The REST API endpoint is centralized in `js/config.js`:
```javascript
const CONFIG = {
    WORDPRESS_API_URL: `http://${window.location.hostname}:8000/wp-json/wp/v2`,
    PROJECTS_ENDPOINT: "/projects",
    EXPERIENCE_ENDPOINT: "/experience",
    REQUEST_TIMEOUT_MS: 5000
};
```
If you deploy WordPress to a live server, XAMPP (`http://localhost/wordpress/wp-json/wp/v2`), or LocalWP (`http://portfolio.local/wp-json/wp/v2`), simply change `WORDPRESS_API_URL` here.

### Using with XAMPP or LocalWP
The custom plugin `portfolio-cms` is standard and portable:
1. Copy the folder `wp-content/plugins/portfolio-cms` into your XAMPP or LocalWP WordPress installation: `wp-content/plugins/`.
2. Go to **Plugins** in WordPress Admin and click **Activate** under **Portfolio CMS - Headless Engine**.
3. The plugin will automatically register the custom post types, REST fields, and CORS headers!

---

## 🔒 Security, CORS & Fallbacks

- **CORS Handling**: The plugin explicitly hooks into `rest_api_init` and `rest_pre_serve_request` to send `Access-Control-Allow-Origin: *` and handle HTTP `OPTIONS` preflight requests cleanly without modifying server config files.
- **Security**: The public frontend only accesses public read endpoints (`GET /wp/v2/projects` and `GET /wp/v2/experience`). No WordPress passwords or tokens are stored in client-side code.
- **Graceful Fallback**: If WordPress is temporarily stopped or unreachable, the frontend displays the existing offline profile data without breaking the page or exposing technical errors to visitors. The deployed HTTPS frontend does not make an insecure CMS request; configure a public HTTPS WordPress API URL in `js/config.js` if you deploy the CMS.
