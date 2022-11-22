This repository is a rudimentary & breaking change from the origin repo. It simply swaps out the 'login' system with support for API Keys.

This class provides the building blocks for someone wanting to use PHP to talk to Proxmox's API.
Relatively simple piece of code, just provides a get/put/post/delete abstraction layer as methods
on top of Proxmox's REST API, while also handling the Login Ticket headers required for authentication.

See http://pve.proxmox.com/wiki/Proxmox_VE_API for information about how this API works.
API spec available at https://pve.proxmox.com/pve-docs/api-viewer/index.html

## Requirements: ##

PHP 5+ with cURL (including SSL/TLS) support.

## Usage: ##

Example - Return status array for each Proxmox Host in this cluster.

    require_once("./pve2-api-php-client/pve2_api.class.php");

    # You can try/catch exception handle the constructor here if you want.
    $pve2 = new PVE2_API("hostname", "username", "realm", "token_id", "token_secret");
    # realm above can be pve, pam or any other realm available.

     foreach ($pve2->get_node_list() as $node_name) {
            print_r($pve2->get("/nodes/".$node_name."/status"));
     }


Licensed under the MIT License.
See LICENSE file.
