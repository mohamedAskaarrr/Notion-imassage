# WhatsApp-Notion AI Assistant

A smart backend that connects WhatsApp to Notion using AI. Send messages on WhatsApp, and our AI intelligently understands your intent and updates your Notion databases automatically.

## 🎯 What It Does

- **Receive** messages from WhatsApp (via Twilio)
- **Understand** what you want using OpenAI (extract intent, entities, actions)
- **Execute** actions in Notion (create tasks, add ideas, update databases)
- **Reply** back to WhatsApp with confirmation

## 🔄 How It Works

```text
WhatsApp Message
       ↓
   Twilio Webhook
       ↓
  AI Parsing (OpenAI)
       ↓
 Notion Action (Add/Update)
       ↓
   WhatsApp Reply
```

## 🚀 Quick Start

### Prerequisites

- PHP 8.1+
- Composer
- Twilio Account (WhatsApp sandbox or production)
- OpenAI API Key
- Notion API Key & Database IDs

### Installation

1. **Clone & Install**

   ```bash
   git clone https://github.com/mohamedAskaarrr/Notion-WhatsApp-Ai-Assistant.git
   cd notion-whatsapp-ai
   composer install
   ```

2. **Configure Environment**

   ```bash
   cp .env.example .env
   ```

   Add your credentials:

   ```env
   TWILIO_ACCOUNT_SID=your_sid
   TWILIO_AUTH_TOKEN=your_token
   OPENAI_API_KEY=your_key
   NOTION_TOKEN=your_token
   NOTION_DATABASE_TASKS=your_db_id
   NOTION_DATABASE_IDEAS=your_db_id
   ```

3. **Run Server**

   ```bash
   php artisan serve
   ```

4. **Test**

   ```bash
   php artisan tinker
   ```

## 📁 Project Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── WhatsAppController.php     # Webhook handler
│   └── Middleware/
│       └── ValidateTwilioRequest.php  # Security validation
├── Services/
│   ├── AIService.php                  # OpenAI integration
│   └── NotionService.php              # Notion integration
└── Traits/
    └── ApiResponse.php                # Response helpers

config/
└── services.php                       # Config mapping

routes/
└── web.php                            # API routes
```

## 🔧 Configuration Files

| File | Purpose |
| --- | --- |
| [SETUP.md](SETUP.md) | Step-by-step installation guide |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Detailed system design |
| [API_CREDENTIALS.md](API_CREDENTIALS.md) | How credentials are used |
| [FILES_CONFIGURATION.md](FILES_CONFIGURATION.md) | File-by-file breakdown |
| [INDEX.md](INDEX.md) | Documentation index |

## 🔐 Security

- Twilio webhook validation enabled
- All credentials in `.env` (never committed)
- Environment variables for all sensitive data
- Middleware for request validation

## 📝 Example Use Cases

- **"Add buy groceries to my tasks"** → Creates task in Notion
- **"Save this startup idea"** → Adds to ideas database
- **"Check my pending tasks"** → Retrieves from Notion
- **"Mark 'meeting' as done"** → Updates task status

## 🛠️ Supported Actions

- ✅ Create tasks in Notion
- ✅ Add items to databases
- ✅ Parse natural language intent
- ✅ WhatsApp webhook integration
- ✅ Multi-database support
- ✅ Flexible AI prompting

## 📚 Documentation

For detailed information, see:

- **New to project?** → [SETUP.md](SETUP.md)
- **Need architecture details?** → [ARCHITECTURE.md](ARCHITECTURE.md)
- **Configuring credentials?** → [API_CREDENTIALS.md](API_CREDENTIALS.md)
- **Quick checklist?** → [FINAL_CHECKLIST.md](FINAL_CHECKLIST.md)

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is open source and available under the MIT License.

## 👨‍💻 Author

Mohamed Askaar

- GitHub: [@mohamedAskaarrr](https://github.com/mohamedAskaarrr)

## 💬 Support

For issues, questions, or suggestions, please [open an issue](https://github.com/mohamedAskaarrr/Notion-WhatsApp-Ai-Assistant/issues) on GitHub.
