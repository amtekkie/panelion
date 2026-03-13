<?php
namespace Panelion\Modules\DNS;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;
use Panelion\Core\Logger;

class DNSController extends Controller
{
    private $db;
    private $cmd;

    private $recordTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR', 'SOA'];

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->cmd = SystemCommand::getInstance();
    }

    public function index()
    {
        $user = $this->app->auth()->user();
        $userId = $user['id'];
        $isAdmin = ($user['role'] === 'admin');

        if ($isAdmin) {
            $zones = $this->db->fetchAll("SELECT z.*, u.username, (SELECT COUNT(*) FROM dns_records WHERE zone_id = z.id) as record_count FROM dns_zones z LEFT JOIN users u ON z.user_id = u.id ORDER BY z.domain");
        } else {
            $zones = $this->db->fetchAll("SELECT z.*, (SELECT COUNT(*) FROM dns_records WHERE zone_id = z.id) as record_count FROM dns_zones z WHERE z.user_id = ? ORDER BY z.domain", [$userId]);
        }

        $this->view('DNS/views/index', [
            'title' => 'DNS Management',
            'zones' => $zones
        ]);
    }

    public function zone($id)
    {
        $user = $this->app->auth()->user();
        $userId = $user['id'];
        $isAdmin = ($user['role'] === 'admin');

        if ($isAdmin) {
            $zone = $this->db->fetch("SELECT z.*, u.username FROM dns_zones z LEFT JOIN users u ON z.user_id = u.id WHERE z.id = ?", [$id]);
        } else {
            $zone = $this->db->fetch("SELECT * FROM dns_zones WHERE id = ? AND user_id = ?", [$id, $userId]);
        }

        if (!$zone) {
            $this->app->session()->flash('error', 'DNS zone not found.');
            $this->redirect('/dns');
            return;
        }

        $records = $this->db->fetchAll("SELECT * FROM dns_records WHERE zone_id = ? ORDER BY type, name", [$id]);

        $this->view('DNS/views/zone', [
            'title' => 'DNS Zone - ' . $zone['domain'],
            'zone' => $zone,
            'records' => $records,
            'recordTypes' => $this->recordTypes
        ]);
    }

    public function createZone()
    {
        $this->view('DNS/views/create', [
            'title' => 'Create DNS Zone'
        ]);
    }

    public function storeZone()
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $userId = $user['id'];

        $domain = strtolower(trim($this->input('domain')));

        if (empty($domain) || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/', $domain)) {
            $this->app->session()->flash('error', 'Invalid domain name.');
            $this->redirect('/dns/create');
            return;
        }

        $existing = $this->db->fetch("SELECT id FROM dns_zones WHERE domain = ?", [$domain]);
        if ($existing) {
            $this->app->session()->flash('error', 'DNS zone already exists for this domain.');
            $this->redirect('/dns/create');
            return;
        }

        try {
            $serial = date('Ymd') . '01';
            $this->db->insert('dns_zones', [
                'user_id' => $userId,
                'domain' => $domain,
                'serial' => $serial,
                'status' => 'active'
            ]);
            $zoneId = $this->db->lastInsertId();

            $serverIp = $this->getServerIP();

            $defaultRecords = [
                ['zone_id' => $zoneId, 'name' => '@', 'type' => 'A', 'content' => $serverIp, 'ttl' => 3600, 'priority' => 0],
                ['zone_id' => $zoneId, 'name' => 'www', 'type' => 'CNAME', 'content' => $domain . '.', 'ttl' => 3600, 'priority' => 0],
                ['zone_id' => $zoneId, 'name' => '@', 'type' => 'MX', 'content' => 'mail.' . $domain . '.', 'ttl' => 3600, 'priority' => 10],
                ['zone_id' => $zoneId, 'name' => 'mail', 'type' => 'A', 'content' => $serverIp, 'ttl' => 3600, 'priority' => 0],
                ['zone_id' => $zoneId, 'name' => '@', 'type' => 'NS', 'content' => 'ns1.' . $domain . '.', 'ttl' => 86400, 'priority' => 0],
                ['zone_id' => $zoneId, 'name' => '@', 'type' => 'NS', 'content' => 'ns2.' . $domain . '.', 'ttl' => 86400, 'priority' => 0],
                ['zone_id' => $zoneId, 'name' => 'ns1', 'type' => 'A', 'content' => $serverIp, 'ttl' => 86400, 'priority' => 0],
                ['zone_id' => $zoneId, 'name' => 'ns2', 'type' => 'A', 'content' => $serverIp, 'ttl' => 86400, 'priority' => 0],
                ['zone_id' => $zoneId, 'name' => '@', 'type' => 'TXT', 'content' => 'v=spf1 a mx ~all', 'ttl' => 3600, 'priority' => 0],
            ];

            foreach ($defaultRecords as $record) {
                $this->db->insert('dns_records', $record);
            }

            $this->writeZoneFile($domain, $zoneId);

            Logger::info("DNS zone created: {$domain}");
            $this->app->session()->flash('success', "DNS zone for '{$domain}' created with default records.");
        } catch (\Exception $e) {
            Logger::error("Failed to create DNS zone: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to create DNS zone.');
        }

        $this->redirect('/dns');
    }

    public function addRecord($zoneId)
    {
        $this->validateCSRF();
        $zone = $this->getZoneForUser($zoneId);
        if (!$zone) return;

        $name = trim($this->input('name'));
        $type = strtoupper(trim($this->input('type')));
        $content = trim($this->input('content'));
        $ttl = (int)$this->input('ttl', 3600);
        $priority = (int)$this->input('priority', 0);

        if (empty($name) || empty($type) || empty($content)) {
            $this->app->session()->flash('error', 'Name, type, and content are required.');
            $this->redirect("/dns/{$zoneId}");
            return;
        }

        if (!in_array($type, $this->recordTypes)) {
            $this->app->session()->flash('error', 'Invalid record type.');
            $this->redirect("/dns/{$zoneId}");
            return;
        }

        if ($name !== '@' && !preg_match('/^[a-zA-Z0-9*._-]+$/', $name)) {
            $this->app->session()->flash('error', 'Invalid record name.');
            $this->redirect("/dns/{$zoneId}");
            return;
        }

        if ($ttl < 60 || $ttl > 604800) {
            $ttl = 3600;
        }

        try {
            $this->db->insert('dns_records', [
                'zone_id' => $zoneId,
                'name' => $name,
                'type' => $type,
                'content' => $content,
                'ttl' => $ttl,
                'priority' => $priority
            ]);

            $this->incrementSerial($zoneId);
            $this->writeZoneFile($zone['domain'], $zoneId);

            Logger::info("DNS record added: {$name} {$type} {$content} in zone {$zone['domain']}");
            $this->app->session()->flash('success', 'DNS record added successfully.');
        } catch (\Exception $e) {
            Logger::error("Failed to add DNS record: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to add DNS record.');
        }

        $this->redirect("/dns/{$zoneId}");
    }

    public function updateRecord($zoneId, $recordId)
    {
        $this->validateCSRF();
        $zone = $this->getZoneForUser($zoneId);
        if (!$zone) return;

        $record = $this->db->fetch("SELECT * FROM dns_records WHERE id = ? AND zone_id = ?", [$recordId, $zoneId]);
        if (!$record) {
            $this->app->session()->flash('error', 'Record not found.');
            $this->redirect("/dns/{$zoneId}");
            return;
        }

        $content = trim($this->input('content'));
        $ttl = (int)$this->input('ttl', 3600);
        $priority = (int)$this->input('priority', 0);

        if (empty($content)) {
            $this->app->session()->flash('error', 'Content is required.');
            $this->redirect("/dns/{$zoneId}");
            return;
        }

        try {
            $this->db->update('dns_records', [
                'content' => $content,
                'ttl' => max(60, min(604800, $ttl)),
                'priority' => $priority
            ], 'id = ?', [$recordId]);

            $this->incrementSerial($zoneId);
            $this->writeZoneFile($zone['domain'], $zoneId);

            $this->app->session()->flash('success', 'DNS record updated.');
        } catch (\Exception $e) {
            Logger::error("Failed to update DNS record: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to update DNS record.');
        }

        $this->redirect("/dns/{$zoneId}");
    }

    public function deleteRecord($zoneId, $recordId)
    {
        $this->validateCSRF();
        $zone = $this->getZoneForUser($zoneId);
        if (!$zone) return;

        try {
            $this->db->deleteFrom('dns_records', 'id = ? AND zone_id = ?', [$recordId, $zoneId]);
            $this->incrementSerial($zoneId);
            $this->writeZoneFile($zone['domain'], $zoneId);

            $this->app->session()->flash('success', 'DNS record deleted.');
        } catch (\Exception $e) {
            Logger::error("Failed to delete DNS record: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to delete DNS record.');
        }

        $this->redirect("/dns/{$zoneId}");
    }

    public function deleteZone($id)
    {
        $this->validateCSRF();
        $zone = $this->getZoneForUser($id);
        if (!$zone) return;

        try {
            $this->db->deleteFrom('dns_records', 'zone_id = ?', [$id]);
            $this->db->deleteFrom('dns_zones', 'id = ?', [$id]);

            $zoneFile = "/etc/bind/zones/db.{$zone['domain']}";
            $this->cmd->execute("rm -f " . escapeshellarg($zoneFile), true);
            $this->removeZoneFromBind($zone['domain']);
            $this->cmd->execute("systemctl reload named 2>/dev/null || systemctl reload bind9 2>/dev/null", true);

            Logger::info("DNS zone deleted: {$zone['domain']}");
            $this->app->session()->flash('success', "DNS zone '{$zone['domain']}' deleted.");
        } catch (\Exception $e) {
            Logger::error("Failed to delete DNS zone: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to delete DNS zone.');
        }

        $this->redirect('/dns');
    }

    // ── Private Helpers ──

    private function getZoneForUser($id)
    {
        $user = $this->app->auth()->user();
        $isAdmin = ($user['role'] === 'admin');

        if ($isAdmin) {
            $zone = $this->db->fetch("SELECT * FROM dns_zones WHERE id = ?", [$id]);
        } else {
            $zone = $this->db->fetch("SELECT * FROM dns_zones WHERE id = ? AND user_id = ?", [$id, $user['id']]);
        }

        if (!$zone) {
            $this->app->session()->flash('error', 'DNS zone not found.');
            $this->redirect('/dns');
            return null;
        }

        return $zone;
    }

    private function getServerIP()
    {
        $ip = $this->cmd->execute("hostname -I | awk '{print $1}'");
        return trim($ip) ?: '127.0.0.1';
    }

    private function incrementSerial($zoneId)
    {
        $zone = $this->db->fetch("SELECT serial FROM dns_zones WHERE id = ?", [$zoneId]);
        $currentSerial = $zone['serial'];
        $today = date('Ymd');

        if (substr($currentSerial, 0, 8) === $today) {
            $seq = (int)substr($currentSerial, 8) + 1;
            $newSerial = $today . str_pad($seq, 2, '0', STR_PAD_LEFT);
        } else {
            $newSerial = $today . '01';
        }

        $this->db->update('dns_zones', ['serial' => $newSerial], 'id = ?', [$zoneId]);
    }

    private function writeZoneFile($domain, $zoneId)
    {
        $zone = $this->db->fetch("SELECT * FROM dns_zones WHERE id = ?", [$zoneId]);
        $records = $this->db->fetchAll("SELECT * FROM dns_records WHERE zone_id = ? ORDER BY type, name", [$zoneId]);

        $serverIp = $this->getServerIP();

        $zoneContent = <<<ZONE
; Zone file for {$domain}
; Generated by Panelion - Do not edit manually
\$TTL 3600
\$ORIGIN {$domain}.

@   IN  SOA ns1.{$domain}. admin.{$domain}. (
        {$zone['serial']}  ; Serial
        3600                ; Refresh
        900                 ; Retry
        1209600             ; Expire
        86400               ; Minimum TTL
    )

ZONE;

        foreach ($records as $record) {
            $name = str_pad($record['name'], 20);
            $ttl = $record['ttl'];
            $type = $record['type'];
            $content = $record['content'];

            if ($type === 'MX' || $type === 'SRV') {
                $zoneContent .= "{$name} {$ttl}  IN  {$type}  {$record['priority']} {$content}\n";
            } else {
                $value = ($type === 'TXT') ? '"' . $content . '"' : $content;
                $zoneContent .= "{$name} {$ttl}  IN  {$type}  {$value}\n";
            }
        }

        $this->cmd->execute("mkdir -p /etc/bind/zones", true);
        $zoneFile = "/etc/bind/zones/db.{$domain}";
        $tmpFile = tempnam(sys_get_temp_dir(), 'pnl_dns_');
        file_put_contents($tmpFile, $zoneContent);
        $this->cmd->execute("cp " . escapeshellarg($tmpFile) . " " . escapeshellarg($zoneFile), true);
        $this->cmd->execute("chmod 644 " . escapeshellarg($zoneFile), true);
        unlink($tmpFile);

        $this->addZoneToBind($domain, $zoneFile);
        $this->cmd->execute("systemctl reload named 2>/dev/null || systemctl reload bind9 2>/dev/null", true);
    }

    private function addZoneToBind($domain, $zoneFile)
    {
        $confFile = "/etc/bind/named.conf.local";
        $check = $this->cmd->execute("grep -c " . escapeshellarg($domain) . " " . escapeshellarg($confFile) . " 2>/dev/null");

        if ((int)trim($check) === 0) {
            $entry = <<<CONF

zone "{$domain}" {
    type master;
    file "{$zoneFile}";
    allow-transfer { none; };
};
CONF;
            $tmpFile = tempnam(sys_get_temp_dir(), 'pnl_bind_');
            file_put_contents($tmpFile, $entry);
            $this->cmd->execute("cat " . escapeshellarg($tmpFile) . " >> " . escapeshellarg($confFile), true);
            unlink($tmpFile);
        }
    }

    private function removeZoneFromBind($domain)
    {
        $confFile = "/etc/bind/named.conf.local";
        $pattern = "/zone \"{$domain}\"/,/^};/d";
        $this->cmd->execute("sed -i " . escapeshellarg($pattern) . " " . escapeshellarg($confFile), true);
    }
}
