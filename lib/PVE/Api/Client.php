<?php
namespace PVE\Api;

/*
Copyright (c) 2012 Nathan Sullivan

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

/**
* 
*/
class Client {
    protected $endpoint;
    protected $port;
    protected $username;
    protected $realm;
    protected $password;

    protected $pve_login_ticket;
    protected $pve_cluster_node_list;

    /**
    * Api client for PVE
    * @param string $endpoint   Hostname or IP
    * @param string $username   Username
    * @param string $realm      Usually "pam", "pve" , etc.
    * @param string $password   Password
    * @return Client
    */
    public function __construct ($endpoint,$port=8006) {
        $this->endpoint = $endpoint;
        $this->port = $port;

        # Default this to null, so we can check later on if were logged in or not.
        $this->pve_login_ticket = null;
        $this->pve_cluster_node_list = null;
    }
    
    //==========================================================================
    // TOOLS
    //==========================================================================
    
    protected function sortByIndex($array){
        $keys=array_keys($array);
        $values=array_values($array);
        array_multisort($keys,$values);
        return array_combine($keys,$values);
    }
    
    protected function httpRequest($method,$path,$content=null){
        # Prepare cURL resource.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $path);

        $headers = array(
            "CSRFPreventionToken: ".$this->pve_login_ticket['CSRFPreventionToken']
        );
        
        # Lets decide what type of action we are taking...
        switch ($method) {
            case "GET": break;
            case "PUT":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');

                # Set body data.
                curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
                unset($action_postfields_string);

                # Add required HTTP headers.
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                break;
                
            case "POST":
                curl_setopt($ch, CURLOPT_POST, true);

                # Set POST data.
                curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
                unset($action_postfields_string);

                # Add required HTTP headers.
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                break;
            case "DELETE":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                # Add required HTTP headers.
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                break;
            default:
                return false;
        }

        //curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, "PVEAuthCookie=".$this->pve_login_ticket['ticket']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);

        curl_close($ch);
        unset($ch);
        
        if($response===false) throw new \Exception(curl_error($ch));
        
        return $response;
    }

    /*
     * Performs login to PVE Server using JSON API, and obtains Access Ticket.
     */
    public function login($username, $realm, $password){
        $this->username = $username;
        $this->realm = $realm;
        $this->password = $password;
        
        # Prepare login variables.
        $login_postfields = array();
        $login_postfields['username'] = $this->username;
        $login_postfields['password'] = $this->password;
        $login_postfields['realm'] = $this->realm;

        $login_postfields_string = http_build_query($login_postfields);
        unset($login_postfields);

        # Perform login request.
        $prox_ch = curl_init();
        curl_setopt($prox_ch, CURLOPT_URL, "https://".$this->endpoint.":".$this->port."/api2/json/access/ticket");
        curl_setopt($prox_ch, CURLOPT_POST, true);
        curl_setopt($prox_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $login_postfields_string);
        curl_setopt($prox_ch, CURLOPT_SSL_VERIFYPEER, false);

        $login_ticket = curl_exec($prox_ch);
        
        curl_close($prox_ch);
        unset($prox_ch);
        unset($login_postfields_string);
        
        if($login_ticket===false) throw new \Exception('Cannot reach endpoint '.$this->endpoint.' on port '.$this->port);

        $login_ticket_data = json_decode($login_ticket, true);
        if($login_ticket_data===null) throw new \Exception('Cannot decode ticket information');
        
        # Login success.
        $this->pve_login_ticket = $login_ticket_data['data'];
        return true;
    }
    
    /*
     * object pve_action (string action_path, string http_method[, array put_post_parameters])
     * This method is responsible for the general cURL requests to the JSON API,
     * and sits behind the abstraction layer methods get/put/post/delete etc.
     */
    protected function pve_action($action_path, $method="GET", $content = null) {
    
        // Ensure root "/" is present
        if(substr($action_path, 0, 1) != "/") $action_path = "/".$action_path;
        
        // Forging url
        $url="https://".$this->endpoint.":".$this->port."/api2/json".$action_path;
        
        // Array style parameters
        if(is_array($content)) $content=http_build_query($put_post_parameters);
        
        $rawAnswer = $this->httpRequest($method,$url,$content);
        if($rawAnswer===false) return false;
        
        $action_response_array = json_decode($rawAnswer, true);

        if ($method == "PUT") return true;
        else return $action_response_array['data'];
    }

    
    public function get_node_list() {
        if ($this->pve_cluster_node_list!=null)return $this->pve_cluster_node_list;
        
        $node_list = $this->pve_action("/nodes");
        if (is_array($node_list)) {
            $nodes_array = array();
            foreach ($node_list as $node) {
                $nodes_array[] = $node['node'];
            }
            $this->pve_cluster_node_list = $nodes_array;
            return $this->pve_cluster_node_list;
        }
        
        throw new \Exception('Cannot retrieve node list');
    }

    //==========================================================================
    // BASIC COMMANDS
    //==========================================================================
    
    /**
    * PVE basic get command 
    * @param string $action_path    Example: /version
    */
    public function get($action_path){
        return $this->pve_action($action_path, "GET");
    }

    /**
    * PVE basic put command
    * @param string $action_path
    * @param array $parameters
    */
    public function put($action_path, $parameters){
        return $this->pve_action($action_path, "PUT", $parameters);
    }

    /**
    * PVE basic post command
    * @param string $action_path
    * @param array $parameters
    */
    public function post($action_path, $parameters){
        return $this->pve_action($action_path, "POST", $parameters);
    }

    /**
    * PVE basic delete command
    * @param string $action_path
    * @param array $parameters
    */
    public function delete($action_path){
        return $this->pve_action($action_path, "DELETE");
    }
    
    //==========================================================================
    // Api scope
    //==========================================================================
    
    /**
    * Get Last VMID from a Cluster or a Node
    */
    public function get_next_vmid() {
        return $this->pve_action("/cluster/nextid");
    }

    /**
    * Get cluster version
    */
    public function get_version() {
        $version = $this->pve_action("/version");
        return $version['version'];
    }
    
    //==========================================================================
    // OpenVZ Scope
    //==========================================================================
    
    /**
    * Get OpenVZ list across the entire cluster
    * @return array
    */
    public function vzlist(){
        $nodes=$this->get_node_list();
        
        $vzList=array();
        foreach($nodes as $node){
            $localVzList=$this->pve_action("/nodes/$node/openvz");
            foreach($localVzList as $vz) $vzList[$vz['vmid']]=$vz;
        }
        
        return $this->sortByIndex($vzList);
    }
    
    //==========================================================================
    // KVM Scope
    //==========================================================================
    
    /**
    * Get KVM list across the entire cluster
    * @return array
    */
    public function kvmlist(){
        $nodes=$this->get_node_list();
        
        $kvmList=array();
        foreach($nodes as $node){
            $localKvmList=$this->pve_action("/nodes/$node/qemu");
            foreach($localKvmList as $kvm){
                $kvm['type']='kvm';
                $kvmList[$kvm['vmid']]=$kvm;  
            } 
        }
        
        return $this->sortByIndex($kvmList);
    }
    
    //==========================================================================
    // Unified OpenVZ + KVM Scope
    //==========================================================================
    
    /**
    * Get vm list across the entire cluster
    * @return array
    */
    public function vmlist(){
        return $this->sortByIndex($this->vzlist() + $this->kvmlist());
    }
    
}
