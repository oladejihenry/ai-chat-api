# Zephfichat - Multi-AI Chat Platform

A modern, multi-model AI chat platform built with **Next.js 15**, **Laravel**, and **TypeScript**. Chat with multiple AI models in a beautiful, responsive interface with conversation history, real-time typing indicators, and seamless model switching.

![Zephfichat Demo](https://youtu.be/b6oltw1ICSI)

## ✨ Features

-   🤖 **Multi-AI Model Support** - Chat with GPT-4o, Claude Sonnet, DeepSeek, and more
-   💬 **Real-time Chat** - Instant message delivery with typing indicators
-   🔄 **Model Switching** - Switch between AI models mid-conversation
-   📱 **Responsive Design** - Beautiful UI that works on desktop and mobile
-   🗂️ **Conversation Management** - Organized by date sections (Today, Yesterday, etc.)
-   🔐 **Secure Authentication** - Laravel Sanctum with Google OAuth
-   🌙 **Dark/Light Mode** - Toggle between themes
-   ⚡ **Optimistic Updates** - Messages appear instantly for smooth UX
-   🔍 **Search Conversations** - Find past conversations quickly
-   ✏️ **Rename & Delete** - Manage your conversation history

## 🛠️ Tech Stack

### Frontend

-   **Next.js 15** with App Router
-   **React 18** with TypeScript
-   **Tailwind CSS** for styling
-   **Shadcn UI** component library
-   **TanStack Query** for state management
-   **Sonner** for notifications

### Backend

-   **Laravel 12** API
-   **Laravel Sanctum** for authentication
-   **PostgreSQL** database

## 🚀 Quick Start

### Prerequisites

-   **Node.js** 18+ and **pnpm**
-   **PHP** 8.2+ and **Composer**
-   **PostgreSQL**
-   AI model API keys (see [AI Model Setup](#-ai-model-setup))

### Frontend Installation

```bash
# Clone the repository
git clone https://github.com/oladejihenry/ai-chat-clone.git
cd ai-chat-clone

# Install dependencies
pnpm install

# Set up environment variables
cp .env.example .env.local

# Configure your environment
# Edit .env.local with your API URLs and keys

# Start development server
pnpm dev
```

### Backend Installation

```bash
# clone the repository
git clone https://github.com/oladejihenry/ai-chat-api.git
cd ai-chat-api

# Install PHP dependencies
composer install

# Set up environment
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env file
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=zephfichat
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations
php artisan migrate

# Start Laravel server
php artisan serve
```

## 🤖 AI Model Setup

Zephfichat supports multiple AI providers. Configure the models you want to use by obtaining API keys and updating your environment variables.

### Supported Models

| Model                | Provider  | Category | Configuration Required |
| -------------------- | --------- | -------- | ---------------------- |
| **GPT-4o**           | OpenAI    | Advanced | OpenAI API Key         |
| **Claude Sonnet 4**  | Anthropic | Creative | Anthropic API Key      |
| **DeepSeek Chat**    | DeepSeek  | Advanced | DeepSeek API Key       |
| **Gemini 2.0 Flash** | Google    | Advanced | Google AI API Key      |

### 1. OpenAI (GPT-4o)

```bash
# Get your API key from: https://platform.openai.com/api-keys
OPENAI_API_KEY=sk-your-openai-api-key-here
```

**Setup Steps:**

1. Visit [OpenAI Platform](https://platform.openai.com/)
2. Create an account or sign in
3. Navigate to API Keys section
4. Create a new API key
5. Add billing information (required for GPT-4)
6. Copy the API key to your `.env` file

### 2. Anthropic (Claude Sonnet 4)

```bash
# Get your API key from: https://console.anthropic.com/
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key-here
```

**Setup Steps:**

1. Visit [Anthropic Console](https://console.anthropic.com/)
2. Create an account or sign in
3. Navigate to API Keys
4. Generate a new API key
5. Copy the key to your `.env` file

### 3. DeepSeek

```bash
# Get your API key from: https://platform.deepseek.com/
DEEPSEEK_API_KEY=sk-your-deepseek-key-here
```

**Setup Steps:**

1. Visit [DeepSeek Platform](https://platform.deepseek.com/)
2. Register for an account
3. Navigate to API section
4. Create an API key
5. Add the key to your `.env` file

### 4. Google AI (Gemini) [Optional]

```bash
# Get your API key from: https://makersuite.google.com/app/apikey
GOOGLE_AI_API_KEY=your-google-ai-key-here
```

**Setup Steps:**

1. Visit [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Sign in with your Google account
3. Create a new API key
4. Copy the key to your `.env` file

### Model Configuration

Edit `lib/aimodels.ts` to enable/disable models:

```typescript
export const aiModels = [
    { name: "GPT-4o", provider: "openai", category: "Advanced" },
    { name: "claude-sonnet-4", provider: "anthropic", category: "Creative" },
    { name: "deepseek-chat", provider: "deepseek", category: "Advanced" },
    // { name: "gemini-2.0-flash", provider: "gemini", category: "Advanced" }, // Uncomment to enable
];
```

## ⚙️ Laravel Backend Configuration

### Environment Setup

Configure your Laravel `.env` file:

```bash
# Application
APP_NAME="Zephfichat API"
APP_ENV=local
APP_KEY=base64:your-generated-key
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zephfichat
DB_USERNAME=root
DB_PASSWORD=

# AI Model APIs
OPENAI_API_KEY=sk-your-openai-key
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key
DEEPSEEK_API_KEY=sk-your-deepseek-key
GOOGLE_AI_API_KEY=your-google-ai-key

# Google OAuth (for authentication)
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret

# Session & Sanctum
SESSION_DRIVER=database
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
```

### Database Schema

The application uses these main tables:

-   `users` - User accounts and profiles
-   `conversations` - Chat conversations with metadata
-   `messages` - Individual messages in conversations

### API Endpoints

| Method   | Endpoint                           | Description              |
| -------- | ---------------------------------- | ------------------------ |
| `GET`    | `/api/user`                        | Get authenticated user   |
| `GET`    | `/api/conversations`               | List user conversations  |
| `POST`   | `/api/conversations`               | Create new conversation  |
| `GET`    | `/api/conversations/{id}`          | Get conversation details |
| `POST`   | `/api/conversations/{id}/messages` | Send message             |
| `PATCH`  | `/api/conversations/{id}`          | Update conversation      |
| `DELETE` | `/api/conversations/{id}`          | Delete conversation      |

## 🔧 Development

### Frontend Development

```bash
# Install dependencies
pnpm install

# Start development server
pnpm dev

# Build for production
pnpm build

# Run tests
pnpm test

# Lint code
pnpm lint
```

### Backend Development

```bash
# Install dependencies
composer install

# Run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed

# Run tests
php artisan test

# Start development server
php artisan serve

# Queue worker (for background jobs)
php artisan queue:work
```

## 🌐 Deployment

### Frontend (Vercel)

```bash
# Build and deploy
pnpm build
vercel --prod
```

### Backend (Laravel Forge/DigitalOcean)

1. Set up your server with PHP 8.2+, PostgreSQL, and Nginx
2. Configure environment variables
3. Run migrations: `php artisan migrate --force`
4. Configure SSL certificate

## 📖 Usage

1. **Sign In** - Use Google OAuth to authenticate
2. **Start Chatting** - Click "New Chat" or select a sample question
3. **Switch Models** - Use the dropdown in the message input to select different AI models
4. **Manage Conversations** - Search, rename, or delete conversations from the sidebar
5. **Organized History** - Conversations are automatically grouped by date (Today, Yesterday, etc.)

### Development Workflow

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes and add tests
4. Commit your changes: `git commit -m 'Add amazing feature'`
5. Push to the branch: `git push origin feature/amazing-feature`
6. Open a Pull Request

## 📄 License

This project is available under multiple licenses. Choose the one that best fits your needs:

### MIT License

```
MIT License

Copyright (c) 2024 Zephfichat

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### Apache License 2.0

```
Apache License
Version 2.0, January 2004
http://www.apache.org/licenses/

Copyright 2024 Zephfichat

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```

### BSD 3-Clause License

```
BSD 3-Clause License

Copyright (c) 2024, Zephfichat
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other other materials provided with the distribution.

3. Neither the name of the copyright holder nor the names of its
   contributors may be used to endorse or promote products derived from
   this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

### License Selection Guide

-   **MIT License**: Most permissive, good for open source projects and commercial use
-   **Apache License 2.0**: Provides patent protection, good for larger projects
-   **BSD 3-Clause**: Simple and permissive, good for academic and commercial use

## 🙏 Acknowledgments

-   [Next.js](https://nextjs.org/) - The React framework for production
-   [Laravel](https://laravel.com/) - The PHP framework for web artisans
-   [Tailwind CSS](https://tailwindcss.com/) - A utility-first CSS framework
-   [Shadcn UI](https://ui.shadcn.com/) - Beautifully designed components
-   [OpenAI](https://openai.com/) - GPT-4 API
-   [Anthropic](https://anthropic.com/) - Claude API
-   [DeepSeek](https://deepseek.com/) - DeepSeek AI API

---

**Built with ❤️ by the Henry**
