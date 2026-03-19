# Notion AI Assistant — Telegram Bot (Make.com Implementation)

> **Submission note:** This is the live, working implementation of the Notion AI Assistant built on Make.com (eu1). A separate draft code-based version exists in this repo for reference, but **the Make.com scenario is the one that works successfully end-to-end today.**

---

## What It Does

A Telegram bot that connects to each user's **personal Notion workspace via OAuth 2.0**. Each team member logs in once through their own Notion account — after that, they can read pages, query databases, and create tasks directly from a Telegram chat. No shared API key. No shared workspace. Fully per-user.

---

## Short Description (for submission)

> A Telegram chatbot built on Make.com that authenticates each user individually against their own Notion workspace via OAuth 2.0. Users can list tasks, check due dates, see status, and create new tasks — all from a single chat message. The system is modular and designed to scale: voice transcription, multi-database commands, and AI intent parsing can be added as the project grows.

---

## Architecture Overview

The system is split into **two independent Make.com scenarios** that work together:

```
┌─────────────────────────────────────────────────────────────┐
│  SCENARIO A — Auth Flow (runs once per user)                │
│                                                             │
│  Telegram sends auth link → User approves in Notion →       │
│  OAuth code received → Token exchanged → Saved to DS        │
└─────────────────────────────────────────────────────────────┘
                              │
                    Token stored in Data Store
                    (key = Telegram chat ID)
                              │
┌─────────────────────────────────────────────────────────────┐
│  SCENARIO B — Main Bot (runs on every message)              │
│                                                             │
│  Telegram message → Check DS for token → Router →          │
│  No token: send auth link                                   │
│  Has token: parse command → call Notion API → reply         │
└─────────────────────────────────────────────────────────────┘
```

---

## Scenario A — Auth Flow

**Purpose:** Authenticate each user against their own Notion workspace. Runs once per user.

**Modules in order:**

| # | Module | What it does |
|---|--------|-------------|
| 1 | Webhooks — Custom webhook | Receives the OAuth callback from Notion after user approves access |
| 2 | HTTP — Make a request | Exchanges the authorization `code` for an `access_token` via `POST /v1/oauth/token` |
| 3 | Data Store — Add/Replace a record | Saves the token with the user's Telegram chat ID as the key |
| 4 | Webhooks — Webhook response | Shows "You're connected! Go back to Telegram and type: my tasks" in the browser |

**How the auth URL is constructed:**

```
https://api.notion.com/v1/oauth/authorize
  ?client_id=YOUR_CLIENT_ID
  &response_type=code
  &owner=user
  &redirect_uri=YOUR_MAKE_WEBHOOK_URL
  &state={{telegram_chat_id}}
```

The `state` parameter carries the user's Telegram chat ID so the token gets saved under the correct key after Notion redirects back.

---

## Scenario B — Main Bot

**Purpose:** Handle every incoming Telegram message and route it to the right Notion action.

**Modules in order:**

| # | Module | What it does |
|---|--------|-------------|
| 1 | Webhooks — Custom webhook | Receives messages from Telegram (connected via `setWebhook`) |
| 2 | Data Store — Get a record | Looks up the user's Notion token by their Telegram chat ID |
| 3 | Router | Splits flow based on token presence and message content |
| 4a | Telegram Bot — Send message | (No token path) Sends the Notion OAuth link to the user |
| 4b | HTTP — Make a request | (Has token path) Calls the Notion API with the user's token |
| 5 | Telegram Bot — Send message | Formats and sends the Notion data back to the user |

**Telegram ↔ Make webhook connection:**

```
https://api.telegram.org/bot{TOKEN}/setWebhook?url={MAKE_WEBHOOK_URL}
```

This is set once in the browser to point Telegram at the Make scenario.

---

## How the Chat Commands Work

Send any of these messages to the bot:

| Command | What happens |
|---------|-------------|
| *(any message, first time)* | Bot sends a Notion OAuth link. Click it, approve access, done. |
| `tasks` | Queries your TASKS database and returns task name, due date, and status |
| `create task [name]` | Creates a new page in your TASKS database with the given name |

**Example replies:**

```
Your Tasks:

1. Finish MLH challenge | 2026-03-15 | In progress
2. New Task |  | Not started
3. Lecture 2 | 2025-10-12 | Not started
```

```
Task created: Finish MLH challenge
```

---

## Data Store Schema

A single Make.com Data Store called `notion_users` stores all user tokens:

| Field | Type | Description |
|-------|------|-------------|
| `key` | Text (primary) | Telegram chat ID |
| `phone` | Text | Telegram chat ID (reference copy) |
| `token` | Text | Notion OAuth access token for this user |

Each user's token is isolated — no user can access another user's data.

---

## Setup Guide

### 1. Notion Integration

- Go to [notion.so/my-integrations](https://www.notion.so/my-integrations)
- Create a new **Public** integration
- Copy the `client_id` and `client_secret`
- Set the redirect URI to your Make.com Scenario A webhook URL

### 2. Telegram Bot

- Message `@BotFather` on Telegram
- Send `/newbot` and follow the steps
- Copy the bot token
- After setting up Scenario B, run this in your browser:

```
https://api.telegram.org/bot{YOUR_TOKEN}/setWebhook?url={SCENARIO_B_WEBHOOK_URL}
```

### 3. Make.com

- Create **Scenario A** with: Webhook → HTTP (token exchange) → Data Store → Webhook response
- Create **Scenario B** with: Webhook → Data Store (get) → Router → HTTP (Notion API) → Telegram Bot
- Turn both scenarios ON with "Immediately as data arrives"

---

## Commands — What Can Be Expanded (If Funded)

The current implementation supports `tasks` and `create task`. The architecture is modular — each new command is simply a new router path. Planned expansions include:

- `ideas` — query the IDEAS database
- `todo` — query the To Do List database
- `done [number]` — mark a task as done by number
- `filter [status]` — show only tasks with a specific status (e.g. "in progress")
- Voice message support — transcribe voice notes and parse them as commands
- AI intent parsing — use an LLM to understand free-form commands instead of keyword matching
- Multi-workspace support — let users connect multiple Notion workspaces
- Notion page creation — create full pages with content, not just tasks

---

## Tech Stack

| Layer | Tool |
|-------|------|
| Automation | Make.com (eu1.make.com) |
| Messaging | Telegram Bot API |
| Auth | Notion OAuth 2.0 (Public integration) |
| Storage | Make.com Data Stores |
| API | Notion REST API v1 |

---

## Repo

[github.com/mohamedAskaarrr/Notion-imassage](https://github.com/mohamedAskaarrr/Notion-imassage)

> The `Make-readme.md` file (this file) documents the live Make.com implementation.  
> The draft code-based version in the repo represents an early exploration of the same concept and is provided for reference only — it is not the submission deliverable.
