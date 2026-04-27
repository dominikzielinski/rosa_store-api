<?php

declare(strict_types=1);

use App\Mcp\Servers\RosaDOroServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Servers
|--------------------------------------------------------------------------
|
| Register MCP (Model Context Protocol) servers exposing Rosa D'oro data
| to Claude Code / similar clients.
|
| ONLY local stdio transport is registered — the web HTTP variant exposes
| contact submission PII (name, email, phone, NIP, IP, marketing consent)
| and there is no MCP-compatible auth layer for the public storefront. If
| a remote use case ever appears, gate it behind `backoffice` middleware.
|
*/

Mcp::local('rosa-doro', RosaDOroServer::class);
