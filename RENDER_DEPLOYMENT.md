# Deploying Emiringkay to Render (render.com)

This guide walks you through deploying **Emiringkay** to [Render](https://render.com) using the included Docker configuration, automated WebSockets (Reverb), and PostgreSQL.

---

## Architecture Overview on Render

- **Web Service (`emiringkay-web`)**:
  - Runs **PHP 8.4** + **Nginx** inside a lightweight Docker container.
  - Hosts both the HTTP application and the **Laravel Reverb WebSocket server** (proxied internally through Nginx on port 8080).
  - Automatically runs database migrations and production caching (`config:cache`, `route:cache`, `view:cache`) upon deployment.
  - Background Queue Worker and Scheduler (hourly ephemeral data purge) run under **Supervisord** within the same container, saving extra service costs.
- **Managed Database (`emiringkay-postgres`)**:
  - Render-managed PostgreSQL database with automatic backups.

---

## Method 1: Instant 1-Click Blueprint Deploy (Recommended)

Render provides Blueprints that automatically read [`render.yaml`](./render.yaml) and configure the web service, database, and environment variables for you:

1. **Push your code to GitHub or GitLab**:
   Ensure all changes are committed and pushed to your repository branch (`main`).

2. **Go to Render**:
   - Open your [Render Dashboard](https://dashboard.render.com).
   - Click **New +** in the top right, then select **Blueprint**.

3. **Connect Repository**:
   - Select your GitHub/GitLab repository containing Emiringkay.
   - Render will detect the `render.yaml` file.

4. **Review & Launch**:
   - Choose your preferred region (e.g. **Singapore** for lowest latency in PH/SEA).
   - Click **Apply**.
   - Render will provision the PostgreSQL database, build the multi-stage Docker container, compile the frontend assets, run migrations, and launch your live application with free automatic SSL (`https://your-app.onrender.com`).

---

## Method 2: Manual Web Service Setup

If you prefer to configure services manually in the Render dashboard:

### Step 1: Create PostgreSQL Database
1. In Render Dashboard, click **New +** -> **PostgreSQL**.
2. Name: `emiringkay-postgres`
3. Database: `emiringkay`
4. User: `emiringkay_user`
5. Region: **Singapore** (or closest to you)
6. Plan: **Free** or **Starter**
7. Click **Create Database**.
8. Once created, copy the **Internal Database URL**.

### Step 2: Create Web Service
1. Click **New +** -> **Web Service**.
2. Connect your Git repository.
3. Configure the service:
   - **Name**: `emiringkay-web`
   - **Language / Environment**: **Docker**
   - **Region**: Same as database (e.g. Singapore)
   - **Branch**: `main`
   - **Plan**: Starter or Free
   - **Health Check Path**: `/up`

### Step 3: Add Environment Variables
In the **Environment** tab of your new Web Service, add the following key-value pairs:

| Key | Value | Description |
|---|---|---|
| `APP_NAME` | `Emiringkay` | Application name |
| `APP_ENV` | `production` | Production environment |
| `APP_DEBUG` | `false` | Disable debug mode for security |
| `APP_KEY` | *(Click "Generate")* or run `php artisan key:generate --show` | 32-char encryption key |
| `DB_CONNECTION` | `pgsql` | PostgreSQL driver |
| `DATABASE_URL` | *(Paste Internal Database URL from Step 1)* | Connection string |
| `BROADCAST_CONNECTION` | `reverb` | Real-time WebSocket broadcasting |
| `QUEUE_CONNECTION` | `database` | Background job processing |
| `SESSION_DRIVER` | `database` | Scalable session storage |
| `CACHE_STORE` | `database` | Shared cache |
| `REVERB_APP_ID` | `759439` | Reverb App ID |
| `REVERB_APP_KEY` | `qsgvmwadpmdqqovwougw` | Reverb Public Key |
| `REVERB_APP_SECRET` | `uhusoopdvye1o9opnju8` | Reverb Secret Key |
| `REVERB_HOST` | `0.0.0.0` | Bind host |
| `REVERB_PORT` | `8080` | Internal port proxied by Nginx |
| `REVERB_SCHEME` | `http` | Internal scheme |
| `VITE_REVERB_APP_KEY` | `qsgvmwadpmdqqovwougw` | Public client WebSocket key |
| `VITE_REVERB_PORT` | `443` | Public TLS WebSocket port |
| `VITE_REVERB_SCHEME` | `https` | Public TLS scheme |

4. Click **Create Web Service**.

---

## Verifying Your Deployment

Once Render finishes building:
1. Open your assigned URL: `https://<your-service-name>.onrender.com`.
2. Check the health check endpoint: `https://<your-service-name>.onrender.com/up` (should return HTTP 200).
3. Test matching on the landing screen, join a Soft Night Plaza space, and test collaborative activities!

---

## Zero-Downtime & Auto Deploy

Every time you `git push` to your connected branch, Render will automatically:
1. Build the updated frontend assets with Vite.
2. Build the optimized PHP 8.4 container.
3. Run zero-downtime health-checked deployment with rollbacks in case of errors.
