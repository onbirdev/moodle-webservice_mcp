# Moodle MCP Web Service Plugin

A Moodle web service plugin that implements the **Model Context Protocol (MCP)** for seamless integration with AI assistants and external systems. This plugin exposes Moodle's external functions as MCP tools using JSON-RPC 2.0, making them discoverable and callable by MCP-compatible clients.

## Features

- **MCP Protocol Implementation**: Full support for Model Context Protocol using JSON-RPC 2.0
- **Dynamic Tool Discovery**: Automatically exposes Moodle external functions as MCP tools
- **JSON Schema Generation**: Converts Moodle parameter descriptions to JSON Schema for better tool documentation
- **Token-based Authentication**: Secure access using Moodle's external service tokens
- **Service-aware**: Only exposes functions available to the authenticated service
- **Well-tested**: Comprehensive PHPUnit test coverage
- **Easy Integration**: Built-in client class for quick integration with other systems

## What is MCP?

The **Model Context Protocol (MCP)** is an open protocol that standardizes how applications provide context to AI assistants and Large Language Models (LLMs). It allows AI assistants to:

- Discover available tools and their capabilities
- Invoke tools with proper parameters
- Receive structured responses

This plugin bridges Moodle's web services with the MCP protocol, enabling AI assistants to interact with your Moodle instance in a standardized way.

## Requirements

- **Moodle**: 4.5 or higher
- **PHP**: 8.1 or higher
- **Moodle Web Services**: Must be enabled

## Installation

### Method 1: Via Moodle Plugin Directory (Recommended)

1. Visit **Site administration → Plugins → Install plugins**
2. Search for "MCP Web Service"
3. Click **Install** and follow the on-screen instructions

### Method 2: Manual Installation

1. Download the plugin or clone from repository:
   ```bash
   cd /path/to/moodle/webservice
   git clone https://github.com/onbirdev/moodle-webservice_mcp.git mcp
   ```

2. Visit **Site administration → Notifications** to complete the installation

3. The plugin will be installed as `webservice_mcp`

## Configuration

### 1. Enable Web Services

1. Go to **Site administration → Advanced features**
2. Enable **Enable web services**
3. Save changes

### 2. Enable MCP Protocol

1. Go to **Site administration → Plugins → Web services → Manage protocols**
2. Enable **Model Context Protocol (MCP)**

### 3. Create an External Service

1. Go to **Site administration → Server → Web services → External services**
2. Click **Add** to create a new service
3. Configure the service:
   - **Name**: e.g., "MCP Service"
   - **Short name**: e.g., "mcp_service"
   - **Enabled**: Yes
   - **Authorized users only**: Yes (recommended)
4. Add the external functions your service should expose

### 4. Create a Token

1. Go to **Site administration → Server → Web services → Manage tokens**
2. Click **Add** to create a new token
3. Select:
   - **User**: The user this token will authenticate as
   - **Service**: The service you created above
4. Save and copy the generated token

### 5. Assign Capability

Ensure users have the `webservice/mcp:use` capability to access the MCP web service.

## Usage

#### Endpoint URL

The MCP server endpoint can be accessed in two ways.

**1. Using query parameter (wstoken):**
```
https://your-moodle-site.com/webservice/mcp/server.php?wstoken=YOUR_TOKEN
```

**2. Using Authorization header (Bearer token):**
```
https://your-moodle-site.com/webservice/mcp/server.php
```

Add the token to the request header:
```
Authorization: Bearer YOUR_TOKEN
```

Replace:
- `your-moodle-site.com` with your Moodle domain
- `YOUR_TOKEN` with the token you generated


#### Monitoring

Monitor MCP web service usage through:
- Standard Moodle logs at **Site administration → Reports → Logs**


### Client Examples

#### 1. Initialize Session

**Request:**
```json
{
   "jsonrpc": "2.0",
   "method": "initialize",
   "params": {},
   "id": 1
}
```

**Response:**
```json
{
   "jsonrpc": "2.0",
   "result": {
      "protocolVersion": "1.0",
      "serverInfo": {
         "name": "Moodle MCP Server",
         "version": "0.1.0"
      },
      "capabilities": {
         "tools": {}
      }
   },
   "id": 1
}
```

#### 2. List Available Tools

**Request:**
```json
{
   "jsonrpc": "2.0",
   "method": "tools/list",
   "params": {},
   "id": 2
}
```

**Response:**
```json
{
   "jsonrpc": "2.0",
   "result": {
      "tools": [
         {
            "name": "core_user_get_users",
            "description": "Search for users matching the criteria",
            "inputSchema": {
               "type": "object",
               "properties": {
                  "criteria": {
                     "type": "array",
                     "items": {
                        "type": "object",
                        "properties": {
                           "key": {
                              "type": "string"
                           },
                           "value": {
                              "type": "string"
                           }
                        }
                     }
                  }
               }
            },
            "outputSchema": {
               "type": "object",
               "properties": {
                  "result": {
                     "type": "object"
                  }
               }
            }
         }
      ]
   },
   "id": 2
}
```

#### 3. Call a Tool

**Request:**
```json
{
   "jsonrpc": "2.0",
   "method": "tools/call",
   "params": {
      "name": "core_user_get_users",
      "arguments": {
         "criteria": [
            {
               "key": "email",
               "value": "student@example.com"
            }
         ]
      }
   },
   "id": 3
}
```

**Response:**
```json
{
   "jsonrpc": "2.0",
   "result": {
      "content": [
         {
            "type": "text",
            "text": "{\"result\":{\"users\":[{\"id\":2,\"username\":\"student\",\"firstname\":\"Student\",\"lastname\":\"User\",\"email\":\"student@example.com\"}]}}"
         }
      ],
      "structuredContent": {
         "result": {
            "users": [
               {
                  "id": 2,
                  "username": "student",
                  "firstname": "Student",
                  "lastname": "User",
                  "email": "student@example.com"
               }
            ]
         }
      }
   },
   "id": 3
}
```

#### Using cURL

```bash
curl -X POST "https://your-moodle-site.com/webservice/mcp/server.php?wstoken=YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/list",
    "params": {},
    "id": 1
  }'
```

```bash
curl -X POST "https://your-moodle-site.com/webservice/mcp/server.php" \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/list",
    "params": {},
    "id": 1
  }'
```


## API Reference

### Supported MCP Methods

| Method | Description | Parameters |
|--------|-------------|------------|
| `initialize` | Initialize MCP session | None |
| `tools/list` | List available tools | None |
| `tools/call` | Invoke a specific tool | `name` (string), `arguments` (object) |


### Error Responses

Errors follow JSON-RPC 2.0 format:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32600,
        "message": "Invalid Request",
        "data": "Missing method"
    },
    "id": null
}
```

**Common Error Codes:**
- `-32700`: Parse error (invalid JSON)
- `-32600`: Invalid request (missing required fields)
- `-32601`: Method not found
- `-32602`: Invalid params
- `-32603`: Internal error

## Architecture

### Components

```
webservice_mcp/
├── classes/
│   ├── local/
│   │   ├── server.php          # MCP server implementation
│   │   ├── request.php         # Request parser and validator
│   │   └── tool_provider.php   # Tool discovery and schema generation
│   └── privacy/
│       └── provider.php        # Privacy API implementation
├── db/
│   └── access.php              # Capability definitions
├── lang/
│   └── en/
│       └── webservice_mcp.php  # Language strings
├── tests/
│   ├── server_test.php         # Server tests
│   ├── client_test.php         # Client tests
│   ├── request_test.php        # Request parser tests
│   └── tool_provider_test.php  # Tool provider tests
├── lib.php                     # Client class
├── locallib.php                # Local library functions
├── server.php                  # Server endpoint
└── version.php                 # Plugin metadata
```

### Data Flow

1. **Client Request** → JSON-RPC 2.0 POST to `server.php`
2. **Authentication** → Token validation via Moodle's web service API
3. **Request Parsing** → `request` class validates JSON-RPC format
4. **Method Routing** → `server` class routes to appropriate handler
5. **Tool Discovery** → `tool_provider` queries available external functions
6. **Schema Generation** → Converts Moodle descriptions to JSON Schema
7. **Tool Execution** → Calls Moodle's external function API
8. **Response** → JSON-RPC 2.0 formatted response

### Key Classes

- **`server`**: Main server implementation extending `webservice_base_server`
- **`request`**: Parses and validates JSON-RPC 2.0 requests
- **`tool_provider`**: Discovers available tools and generates JSON Schemas
- **`webservice_mcp_client`**: Client for making MCP requests

## Testing

### Running Tests

```bash
# Run all plugin tests
vendor/bin/phpunit --testsuite webservice_mcp_testsuite
```

### Test Coverage

The plugin includes comprehensive tests for:
- ✅ JSON-RPC 2.0 request parsing and validation
- ✅ MCP protocol methods (initialize, tools/list, tools/call)
- ✅ Tool discovery and schema generation
- ✅ Client class functionality
- ✅ Error handling and edge cases

## Troubleshooting

### Common Issues

#### 1. "Web services are not enabled"
**Solution**: Enable web services in **Site administration → Advanced features**

#### 2. "Invalid token"
**Solution**: 
- Verify the token is correct
- Check the token hasn't expired
- Ensure the service is enabled
- Confirm the user has appropriate capabilities

#### 3. "Method not found"
**Solution**: Check that the method name is correct:
- `initialize`
- `tools/list`
- `tools/call`

#### 4. "Missing tool name"
**Solution**: When using `tools/call`, ensure you provide the `name` parameter:
```json
{
    "method": "tools/call",
    "params": {
        "name": "core_user_get_users",
        "arguments": {}
    }
}
```

#### 5. Empty tools list
**Solution**:
- Check that your service has functions added
- Verify the token is associated with the correct service
- Ensure functions aren't deprecated

### Logging

MCP requests are logged in Moodle's standard web service logs:
- **Site administration → Reports → Logs**
- Filter by "Web service" component


## 💖 Support the development of this plugin

Keep it updated and free for everyone!

[☕ Buy Me a Coffee (Ko-fi)](https://ko-fi.com/onbirdev) | [💸 Support via PayPal](https://www.paypal.me/onbirdev)
