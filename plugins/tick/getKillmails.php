<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 Robert Sardinia
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/**
 * Class getKillmails
 */
class getKillmails
{
    /**
     * @var
     */
    var $config;
    /**
     * @var
     */
    var $db;
    /**
     * @var
     */
    var $discord;
    /**
     * @var
     */
    var $channelConfig;
    /**
     * @var int
     */
    var $lastCheck = 0;
    /**
     * @var
     */
    var $logger;
    public $newestKillmailID;
    public $kmChannel;
    public $corpID;
    public $startMail;

    /**
     * @param $config
     * @param $discord
     * @param $logger
     */
    function init($config, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->kmChannel = $config["plugins"]["getKillmails"]["channel"];
        $this->corpID = $config["plugins"]["getKillmails"]["corpID"];
        $this->startMail = $config["plugins"]["getKillmails"]["startMail"];
        if(2 > 1) // Schedule it for right now
            setPermCache("killmailCheck{$this->corpID}", time() - 5);
    }



    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "",
            "trigger" => array(),
            "information" => ""
        );
    }

    /**
     *
     */
    function tick()
    {
        $lastChecked = getPermCache("killmailCheck{$this->corpID}");
        if ($lastChecked <= time()) {
            $this->logger->info("Checking for new killmails.");
            $oldID = getPermCache("newestKillmailID");
            $one = '1';
            $updatedID = $oldID + $one;
            setPermCache("newestKillmailID", $updatedID);
            $this->getKM();
            setPermCache("killmailCheck{$this->corpID}", time() + 600);
        }

    }

    function getKM()
    {
        $this->newestKillmailID = getPermCache("newestKillmailID");
        $lastMail = $this->newestKillmailID;
        $url = "https://zkillboard.com/api/xml/no-attackers/no-items/orderDirection/asc/afterKillID/{$lastMail}/corporationID/{$this->corpID}";
        $xml = simplexml_load_file($url);
        $kills = $xml->result->rowset->row;
        $i = 0;
        $limit = 15;
        foreach ($kills as $kill) {
            if ($i < $limit){
                $killID = $kill->attributes()->killID;
                $solarSystemID = $kill->attributes()->solarSystemID;
                $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $solarSystemID), "ccp");
                $killTime = $kill->attributes()->killTime;
                $victimAllianceName = $kill->victim->attributes()->allianceName;
                $victimName = $kill->victim->attributes()->characterName;
                $victimCorpName = $kill->victim->attributes()->corporationName;
                $victimShipID = $kill->victim->attributes()->shipTypeID;
                $shipName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => $victimShipID), "ccp");
                // Check if it's a structure
                if ($victimName != "") {
                    $msg = "**{$killTime}**\n\n**{$shipName}** flown by **{$victimName}** of (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                }
                elseif ($victimName == ""){
                    $msg = "**{$killTime}**\n\n**{$shipName}** of (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                }
                $this->discord->api("channel")->messages()->create($this->kmChannel, $msg);
                setPermCache("newestKillmailID", $killID);

                sleep (2);
                $i++;
            }
            else {
                $updatedID = getPermCache("newestKillmailID");
                $this->logger->info("Kill posting cap reached, newest kill id is {$updatedID}");
                return null;
            }
        }
        $updatedID = getPermCache("newestKillmailID");
        $this->logger->info("All kills posted, newest kill id is {$updatedID}");
        return null;
    }

    /**
     * @param $msgData
     */
    function onMessage($msgData)
    {
    }
}