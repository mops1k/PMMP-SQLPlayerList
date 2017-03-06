<?php
namespace mops1k\SQLPlayerList\Listener;

use BasePlugin\Plugin;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

/**
 * Class PlayerListener
 * @package mops1k\SQLPlayerList\Listener
 */
class PlayerListener implements Listener
{
    const STATUS_OFFLINE = 0;
    const STATUS_ONLINE = 1;

    private $plugin;
    private $table;

    /**
     * PlayerListener constructor.
     */
    public function __construct()
    {
        $this->plugin = Plugin::getInstance();
        if ($this->plugin->db->getORM() === null) {
            throw new \PDOException('No database configuration!');
        }

        $this->table = $this->plugin->getConfig()->getNested('database')['table'];

        $this->checkTable();
    }

    /**
     * @param PlayerJoinEvent $event
     */
    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        $this->updatePlayer($player, self::STATUS_ONLINE);
    }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        $this->updatePlayer($player, self::STATUS_OFFLINE);
    }

    /**
     * Checking table exists
     */
    private function checkTable()
    {
        $orm = $this->plugin->db->getORM();
        $query = $orm->query("SHOW TABLES LIKE '".$this->table."'");
        $exists = false;
        if ($query->execute()) {
            if ($query->rowCount() == 1) {
                $exists = true;
            }
        }

        if (!$exists) {
            $query = $orm->query(
                'CREATE TABLE `' . $this->table . '` (' . "\n"
                . "`id` int NOT NULL AUTO_INCREMENT,\n"
                . "`server` VARCHAR(50) NULL,\n"
                . "`player` VARCHAR(50) NULL,\n"
                . "`status` SMALLINT NULL DEFAULT NULL,\n"
                . "`total_time` INT NULL DEFAULT '0',\n"
                . "`last_login` DATETIME NULL DEFAULT NULL,\n"
                . "`last_logout` DATETIME NULL DEFAULT NULL,\n"
                . "PRIMARY KEY (ID)\n"
                . ")"
            );
            $query->execute();
        }
    }

    /**
     * @param Player $player
     * @param $status
     */
    private function updatePlayer(Player $player, $status)
    {
        $orm = $this->plugin->db->getORM();
        $server = $this->plugin->getConfig()->get('server');
        $sqlPlayer = $orm->table($this->table)->where(
            [
                'player'   => $player->getName(),
                'server'   => $server
            ]
        )->limit(1);

        $date = new \DateTime();
        if ($sqlPlayer->rowCount() === 0) {
            $sqlPlayer = $orm->createRow($this->table, [
                'server'     => $server,
                'player'     => $player->getName(),
                'total_time' => 0
            ]);
        } else {
            $sqlPlayer = $sqlPlayer->fetch();
        }

        switch ($status) {
            case self::STATUS_ONLINE:
                $sqlPlayer->update([
                    'last_login'    => $date->format('Y-m-d H:i:s'),
                    'status'        => $status,
                ]);
                break;
            case self::STATUS_OFFLINE:
                $lastLoginTimestamp = strtotime($sqlPlayer->last_login);
                $lastLogoutTimestamp = strtotime($date->format('Y-m-d H:i:s'));
                $onlineTime = $lastLogoutTimestamp - $lastLoginTimestamp + $sqlPlayer->total_time;
                $sqlPlayer->update([
                    'total_time'    => $onlineTime,
                    'last_logout'   => $date->format('Y-m-d H:i:s'),
                    'status'        => $status,
                ]);
                break;
        }

        $orm->begin();
        $sqlPlayer->save();
        $orm->commit();
    }
}
