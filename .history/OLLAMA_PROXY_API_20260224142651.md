# Ollama Proxy API Documentation

This Laravel application provides a secure proxy gateway to your Ollama server, allowing mobile apps and external projects to access Ollama API with authentication.

## Base URL

```
https://your-domain.com/api/ollama
```

## Authentication

All endpoints (except `/health`) require Laravel Sanctum authentication.

### Getting an API Token

1. Create a token for your user:

```bash
php artisan tinker
$user = User::find(1);
$token = $user->createToken('mobile-app')->plainTextToken;
echo $token;
```

2. Use the token in your requests:

```
Authorization: Bearer YOUR_TOKEN_HERE
```

## Endpoints

### 1. Health Check (Public)

Check if Ollama server is running.

```bash
GET /api/ollama/health
```

**Response:**

```json
{
    "status": "ok",
    "ollama_running": true,
    "response": "Ollama is running"
}
```

---

### 2. Chat Completion

Generate chat responses with context.

```bash
POST /api/ollama/chat
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "model": "llama3.2:1b",
    "messages": [
        {
            "role": "system",
            "content": "You are a helpful assistant."
        },
        {
            "role": "user",
            "content": "Why is the sky blue?"
        }
    ],
    "tools": [],
    "stream": false,
    "think": false
}
```

**Response:**

```json
{
    "model": "llama3.2:1b",
    "created_at": "2026-02-24T10:30:00Z",
    "message": {
        "role": "assistant",
        "content": "The sky appears blue because..."
    },
    "done": true
}
```

**Streaming Example:**

```json
{
  "model": "llama3.2:1b",
  "messages": [...],
  "stream": true
}
```

Returns: NDJSON stream of partial responses

---

### 3. Generate Completion

Generate text completion from a prompt.

```bash
POST /api/ollama/generate
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "model": "llama3.2:1b",
    "prompt": "Write a haiku about construction",
    "suffix": "",
    "images": [],
    "stream": false,
    "think": false
}
```

**Response:**

```json
{
    "model": "llama3.2:1b",
    "created_at": "2026-02-24T10:30:00Z",
    "response": "Steel beams rising high...",
    "done": true
}
```

---

### 4. List Models

Get all available models.

```bash
GET /api/ollama/tags
Authorization: Bearer YOUR_TOKEN
```

**Response:**

```json
{
    "models": [
        {
            "name": "llama3.2:1b",
            "modified_at": "2026-02-20T10:00:00Z",
            "size": 1300000000,
            "digest": "sha256:abc123...",
            "details": {
                "format": "gguf",
                "family": "llama",
                "families": ["llama"],
                "parameter_size": "1B",
                "quantization_level": "Q4_0"
            }
        }
    ]
}
```

---

### 5. Show Model Info

Get detailed information about a specific model.

```bash
POST /api/ollama/show
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "name": "llama3.2:1b"
}
```

---

### 6. Pull Model

Download a model from Ollama library.

```bash
POST /api/ollama/pull
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "model": "llama3.2:1b",
    "stream": true
}
```

Returns: Streaming progress updates

---

### 7. Generate Embeddings

Create embeddings for text.

```bash
POST /api/ollama/embed
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "model": "nomic-embed-text:latest",
    "input": "Construction project management",
    "truncate": true
}
```

**Response:**

```json
{
    "model": "nomic-embed-text:latest",
    "embeddings": [[0.123, -0.456, 0.789, ...]],
    "total_duration": 14143917,
    "load_duration": 1019500,
    "prompt_eval_count": 8
}
```

**Note:** `input` can be a string or an array of strings for batch embedding generation.

---

### 8. Delete Model

Remove a model from the system.

```bash
DELETE /api/ollama/delete
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "model": "llama3.2:1b"
}
```

---

### 9. Copy Model

Create a copy of a model with a new name.

```bash
POST /api/ollama/copy
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "source": "llama3.2:1b",
    "destination": "my-custom-model"
}
```

---

### 10. Create Model

Create a custom model from a Modelfile.

```bash
POST /api/ollama/create
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "model": "my-custom-model",
    "from": "llama3.2:1b",
    "system": "You are a construction expert.",
    "parameters": {
        "temperature": 0.8,
        "num_ctx": 4096
    },
    "stream": true
}
```

---

## Mobile App Examples

### Flutter/Dart

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

class OllamaClient {
  final String baseUrl = 'https://your-domain.com/api/ollama';
  final String token = 'YOUR_TOKEN_HERE';

  Future<Map<String, dynamic>> chat(String model, List<Map<String, String>> messages) async {
    final response = await http.post(
      Uri.parse('$baseUrl/chat'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'model': model,
        'messages': messages,
        'stream': false,
      }),
    );

    return jsonDecode(response.body);
  }

  Future<List<dynamic>> listModels() async {
    final response = await http.get(
      Uri.parse('$baseUrl/tags'),
      headers: {
        'Authorization': 'Bearer $token',
      },
    );

    final data = jsonDecode(response.body);
    return data['models'];
  }
}

// Usage
void main() async {
  final client = OllamaClient();

  final result = await client.chat('llama3.2:1b', [
    {'role': 'user', 'content': 'Hello!'}
  ]);

  print(result['message']['content']);
}
```

### React Native/JavaScript

```javascript
const OLLAMA_BASE_URL = 'https://your-domain.com/api/ollama';
const API_TOKEN = 'YOUR_TOKEN_HERE';

async function chat(model, messages) {
    const response = await fetch(`${OLLAMA_BASE_URL}/chat`, {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${API_TOKEN}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            model,
            messages,
            stream: false,
        }),
    });

    return await response.json();
}

async function listModels() {
    const response = await fetch(`${OLLAMA_BASE_URL}/tags`, {
        headers: {
            Authorization: `Bearer ${API_TOKEN}`,
        },
    });

    const data = await response.json();
    return data.models;
}

// Usage
(async () => {
    const result = await chat('llama3.2:1b', [
        { role: 'user', content: 'Hello!' },
    ]);

    console.log(result.message.content);

    const models = await listModels();
    console.log(
        'Available models:',
        models.map((m) => m.name),
    );
})();
```

### Swift (iOS)

```swift
import Foundation

class OllamaClient {
    let baseURL = "https://your-domain.com/api/ollama"
    let token = "YOUR_TOKEN_HERE"

    func chat(model: String, messages: [[String: String]], completion: @escaping (Result<[String: Any], Error>) -> Void) {
        guard let url = URL(string: "\(baseURL)/chat") else { return }

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")

        let body: [String: Any] = [
            "model": model,
            "messages": messages,
            "stream": false
        ]

        request.httpBody = try? JSONSerialization.data(withJSONObject: body)

        URLSession.shared.dataTask(with: request) { data, response, error in
            if let error = error {
                completion(.failure(error))
                return
            }

            guard let data = data,
                  let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else {
                return
            }

            completion(.success(json))
        }.resume()
    }
}

// Usage
let client = OllamaClient()
client.chat(model: "llama3.2:1b", messages: [
    ["role": "user", "content": "Hello!"]
]) { result in
    switch result {
    case .success(let data):
        if let message = data["message"] as? [String: Any],
           let content = message["content"] as? String {
            print(content)
        }
    case .failure(let error):
        print("Error: \(error)")
    }
}
```

## Rate Limiting

- Authenticated endpoints: **120 requests per minute**
- Health check: No rate limit

## Error Responses

```json
{
    "error": "Error message describing what went wrong"
}
```

Common HTTP status codes:

- `200` - Success
- `401` - Unauthorized (invalid or missing token)
- `422` - Validation error
- `429` - Rate limit exceeded
- `500` - Ollama service error
- `503` - Ollama server not running

## Configuration

Edit `.env` to configure Ollama connection:

```env
OLLAMA_BASE_URL=http://localhost:11434
```

## Security Notes

- Always use HTTPS in production
- Keep your API tokens secure
- Rotate tokens regularly
- Use different tokens for different apps/purposes
- Monitor API usage via Laravel logs
