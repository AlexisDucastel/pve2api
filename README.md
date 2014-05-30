This class implements a simple PHP client for Proxmox API (version 2 or later).

See http://pve.proxmox.com/wiki/Proxmox_VE_API for information about how this API works.
API spec available at http://pve.proxmox.com/pve2-api-doc/

## Requirements: ##

PHP 5 with cURL (including SSL) support.

## Usage: ##

Example - Return status array for each Proxmox Host in this cluster.

    # use composer autoloader
    require_once __DIR__."/vendor/autoload.php";

    $api=new PVE\Api\Client('hostname');
    
    # realm can be pve, pam or any other realm available.
    $api->login('login','realm','password');

    foreach ($api->get_node_list() as $node_name) {
        print_r($api->get("/nodes/".$node_name."/status"));
    }
    
Example - Create a new OpenVZ Container on the first host in the cluster.

    # use composer autoloader
    require_once __DIR__."/vendor/autoload.php";

    $api=new PVE\Api\Client('hostname');
    
    # realm can be pve, pam or any other realm available.
    $api->login('login','realm','password');

    # Get first node name.
    $nodes = $api->get_node_list();

    # Create a VZ container on the first node in the cluster.
    $new_container_settings = array();
    $new_container_settings['ostemplate'] = "local:vztmpl/debian-6.0-standard_6.0-4_amd64.tar.gz";
    $new_container_settings['vmid'] = "1234";
    $new_container_settings['cpus'] = "2";
    $new_container_settings['description'] = "Test VM using Proxmox 2.0 API";
    $new_container_settings['disk'] = "8";
    $new_container_settings['hostname'] = "testapi.domain.tld";
    $new_container_settings['memory'] = "1024";
    $new_container_settings['nameserver'] = "8.8.8.8";

    print_r($api->post("/nodes/".$nodes[0]."/openvz", $new_container_settings));

Example - Modify DNS settings on an existing container on the first host.

    # use composer autoloader
    require_once __DIR__."/vendor/autoload.php";

    $api=new PVE\Api\Client('hostname');
    
    # realm can be pve, pam or any other realm available.
    $api->login('login','realm','password');

    # Get first node name.
    $nodes = $api->get_node_list();
    $openvzId = "100";
            
    # Update container settings.
    $container_settings = array();
    $container_settings['nameserver'] = "4.2.2.2";

    var_dump($api->put("/nodes/".$first_node."/openvz/".$openvzId."/config", $container_settings));

Example - Delete an existing container.

    # use composer autoloader
    require_once __DIR__."/vendor/autoload.php";

    $api=new PVE\Api\Client('hostname');
    
    # realm can be pve, pam or any other realm available.
    $api->login('login','realm','password');

    # Get first node name.
    $nodes = $api->get_node_list();
    $openvzId = "100";
            
    var_dump($api->delete("/nodes/".$nodes[0]."/openvz/".$openvzId));
    
Licensed under the MIT License.
See LICENSE file.
