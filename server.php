<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * MCP web service entry point.
 *
 * This is the main entry point for the MCP (Model Context Protocol) web service.
 * Authentication is performed via tokens, supporting both Bearer tokens in the
 * Authorization header and token parameters in the URL.
 *
 * @package     webservice_mcp
 * @author      MohammadReza PourMohammad <onbirdev@gmail.com>
 * @copyright   2025 MohammadReza PourMohammad
 * @link        https://onbir.dev
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Disable Moodle-specific debug messages and error output.
use webservice_mcp\local\server;

define('NO_DEBUG_DISPLAY', true);

// Mark this as a web service server script.
define('WS_SERVER', true);

require('../../config.php');

// Check if the MCP protocol is enabled.
if (!webservice_protocol_is_enabled('mcp')) {
    header("HTTP/1.0 403 Forbidden");
    debugging(
        'The server died because the web services or the MCP protocol are not enabled',
        DEBUG_DEVELOPER
    );
    die;
}

// Instantiate and run the MCP server.
$server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
$server->run();
die;
