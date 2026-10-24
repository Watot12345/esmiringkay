# Deploying Emiringkay to Render (100% Free - $0)

You do **NOT** need to pay to deploy Emiringkay to Render. 

The previous configuration had `plan: starter` (Render's $7/mo tier). We have updated it to **`plan: free`** with embedded **SQLite**, so you can publish your app **completely free ($0/month)** with zero credit card charges.

---

## Why Render Asked for Payment Before
1. **Paid Plan in Blueprint**: The initial template had `plan: starter` ($7/mo) and a managed PostgreSQL database ($7/mo).
2. **Now Updated to 100% Free**: We changed `render.yaml` to **`plan: free`** and switched the database to lightweight **SQLite** stored inside the container. This eliminates the need for a separate paid database!

---

## 100% Free Deployment Options

### Option A: Manual Web Service (Easiest & Guaranteed Free - No Blueprint)

If Render asks for a credit card during Blueprint import, use this manual method which is guaranteed **Free ($0)** and never asks for payment:

1. Go to your [Render Dashboard](https://dashboard.render.com).
2. Click **New +** in the top right -> **Web Service**.
3. Connect your GitHub/GitLab repository (`esmiringkay`).
4. Fill in the basic settings:
   - **Name**: `emiringkay-web`
   - **Region**: **Singapore** (or closest to you)
   - **Branch**: `main`
   - **Language / Runtime**: Select **Docker** (it will automatically find the `Dockerfile`)
   - **Instance Type / Plan**: Select **Free ($0/month)**
5. Expand **Advanced** and set **Health Check Path**: `/up`
6. Add these Environment Variables:
   - `APP_NAME` = `Emiringkay`
   - `APP_ENV` = `production`
   - `APP_DEBUG` = `false`
   - `APP_KEY` = `base64:lIaIQLTRzpkDFv/Rv7a2UY7yKMjReXWj2KaOSd4aeao=` *(or generate your own)*
   - `DB_CONNECTION` = `sqlite`
   - `BROADCAST_CONNECTION` = `reverb`
   - `QUEUE_CONNECTION` = `database`
   - `SESSION_DRIVER` = `database`
   - `CACHE_STORE` = `database`
   - `REVERB_APP_ID` = `759439`
   - `REVERB_APP_KEY` = `qsgvmwadpmdqqovwougw`
   - `REVERB_APP_SECRET` = `uhusoopdvye1o9opnju8`
   - `REVERB_HOST` = `0.0.0.0`
   - `REVERB_PORT` = `8080`
   - `REVERB_SCHEME` = `http`
7. Click **Create Web Service**. It will build and go live for free at `https://<your-app>.onrender.com`.

---

### Option B: Using the Updated Free Blueprint
1. Commit and push the updated `render.yaml`:
   ```bash
   git push origin main
   ```
2. Go to **New +** -> **Blueprint** in Render.
3. Select your repository. Notice the plan is now **Free** and total monthly cost is **$0**.
4. Click **Apply**.
