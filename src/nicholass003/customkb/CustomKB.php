<?php

/*
 * Copyright (c) 2024 - present nicholass003
 *        _      _           _                ___   ___ ____
 *       (_)    | |         | |              / _ \ / _ \___ \
 *  _ __  _  ___| |__   ___ | | __ _ ___ ___| | | | | | |__) |
 * | '_ \| |/ __| '_ \ / _ \| |/ _` / __/ __| | | | | | |__ <
 * | | | | | (__| | | | (_) | | (_| \__ \__ \ |_| | |_| |__) |
 * |_| |_|_|\___|_| |_|\___/|_|\__,_|___/___/\___/ \___/____/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  nicholass003
 * @link    https://github.com/nicholass003/
 *
 *
 */

declare(strict_types=1);

namespace nicholass003\customkb;

use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\FormIcon;
use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class CustomKB extends PluginBase implements Listener{

    public const PREFIX = "§6[§eCustomKB§6]§r\n";

    /** @var string[] */
    private array $disabledWorlds = [];

    public const TYPE_ATTACKDELAY = 0;
    public const TYPE_KNOCKBACK = 1;

    public Config $data;

    protected function onLoad() : void{
        $this->saveDefaultConfig();

        $disabledWorlds = $this->getConfig()->get("disabled-worlds", []);
        if(count($disabledWorlds) > 0){
            foreach($disabledWorlds as $disabledWorld){
                $this->disabledWorlds[] = $disabledWorld;
            }
        }
    }

    protected function onEnable() : void{
        $this->data = new Config($this->getDataFolder() . "data.json", Config::JSON);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if($sender instanceof Player){
            if($command->getName() === "customkb"){
                $worldName = $sender->getWorld()->getFolderName();
                if(isset($args[0])){
                    switch(strtolower($args[0])){
                        case "help":
                            $sender->sendMessage(self::PREFIX . TextFormat::YELLOW . "Help Information");
                            $sender->sendMessage(TextFormat::GREEN . "- /help Help Information");
                            $sender->sendMessage(TextFormat::GREEN . "- /options Options Information");
                            $sender->sendMessage(TextFormat::GREEN . "- /set <options> <value> Setup CustomKB");
                            $sender->sendMessage(TextFormat::GREEN . "- /settings Open Settings UI (aliases /ui)");
                            break;
                        case "option":
                        case "options":
                            $sender->sendMessage(self::PREFIX . TextFormat::YELLOW . "Options Information");
                            $sender->sendMessage(TextFormat::GREEN . "- attackdelay (Set Custom Attackdelay to the World)");
                            $sender->sendMessage(TextFormat::GREEN . "- knockback (Set Custom Knockback to the World)");
                            break;
                        case "set":
                            if(isset($args[1])){
                                if(isset($args[2])){
                                    $type = match(strtolower($args[2])){
                                        "attackdelay" => self::TYPE_ATTACKDELAY,
                                        "knockback" => self::TYPE_KNOCKBACK,
                                        default => throw new \InvalidArgumentException("Invalid type value")
                                    };
                                    $configure = $this->configure($worldName, $type, (float) $args[2]);
                                    $this->sendMessage($sender, $this->getTarget($type), (float) $args[2], $configure);
                                }else{
                                    $sender->sendMessage(TextFormat::RED . "Usage: /customkb set " . $args[1] . " <value>");
                                }
                            }else{
                                $sender->sendMessage(TextFormat::RED . "Usage: /customkb set [options]");
                            }
                            break;
                        case "setting":
                        case "settings":
                        case "ui":
                            $this->openSettingsForm($sender);
                            break;
                        case "show":
                            $attackDelay = $this->get($worldName, self::TYPE_ATTACKDELAY) ?? "default";
                            $knockBack = $this->get($worldName, self::TYPE_KNOCKBACK) ?? "default";
                            $sender->sendMessage(self::PREFIX . TextFormat::YELLOW . "CustomKB Information\n" . TextFormat::YELLOW . "World: " . $worldName . "\n");
                            $sender->sendMessage(TextFormat::GREEN . "AttackDelay : {$attackDelay}\n" . TextFormat::GREEN . "KnockBack : {$knockBack}");
                            break;
                    }
                }else{
                    $sender->sendMessage(TextFormat::RED . "Usage: /customkb <help:options:set:settings");
                }
            }
        }
        return true;
    }

    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void{
        $victim = $event->getEntity();
        $damager = $event->getDamager();
        if($victim instanceof Living && $damager instanceof Player){
            $worldName = $victim->getWorld()->getFolderName();
            if(!in_array($worldName, $this->disabledWorlds)){
                $attackCd = $this->get($worldName, self::TYPE_ATTACKDELAY);
                if($attackCd !== null){
                    $event->setAttackCooldown((int) $attackCd);
                }
                $knockBack = $this->get($worldName, self::TYPE_KNOCKBACK);
                if($knockBack !== null){
                    $event->setKnockBack((float) $knockBack);
                }
            }
        }
    }

    public function openSettingsForm(Player $player) : void{
        $form = new MenuForm(
            "§eCustomKB Settings",
            "§fConfigure your CustomKB",
            [
                new MenuOption("AttackDelay", new FormIcon("textures/items/clock_item", FormIcon::IMAGE_TYPE_PATH)),
                new MenuOption("KnockBack", new FormIcon("textures/items/fireball", FormIcon::IMAGE_TYPE_PATH))
            ],
            function(Player $submitter, int $option) : void{
                switch($option){
                    case 0:
                        $this->openSetupForm($submitter, self::TYPE_ATTACKDELAY);
                        break;
                    case 1:
                        $this->openSetupForm($submitter, self::TYPE_KNOCKBACK);
                        break;
                }
            }
        );
        $player->sendForm($form);
    }

    public function openSetupForm(Player $player, int $type) : void{
        $target = $this->getTarget($type);
        $form = new CustomForm(
            "§e{$target} Settings",
            [
                new Input("input_kb", "§fInput {$target} value", "§fvalue must be more than 0")
            ],
            function(Player $submitter, CustomFormResponse $response) use($target, $type) : void{
                $value = $this->getValue($response->getString("input_kb"));
                if($value === false){
                    $submitter->sendMessage(TextFormat::RED . "Failed to submit, numeric value only!");
                    return;
                }
                $this->sendMessage($submitter, $target, $value, $this->configure($submitter->getWorld()->getFolderName(), $type, $value));
            }
        );
        $player->sendForm($form);
    }

    private function configure(string $worldName, int $type, float $value) : bool{
        if(in_array($worldName, $this->disabledWorlds) || $value <= 0){
            return false;
        }
        if($type === self::TYPE_ATTACKDELAY){
            $value = (int) $value;
        }
        $target = strtolower($this->getTarget($type));
        $data = $this->data->get($worldName);
        if(!empty($data)){
            $this->data->set($worldName, array_merge($data, [$target => $value]));
        }else{
            $this->data->set($worldName, [$target => $value]);
        }
        $this->data->save();
        return true;
    }

    private function get(string $worldName, int $type) : float|int|null{
        $target = strtolower($this->getTarget($type));
        $data = $this->data->get($worldName);
        return $data[$target] ?? null;
    }

    private function sendMessage(Player $player, string $target, float|int $value, bool $configure) : void{
        if($configure === true){
            $player->sendMessage("§aSuccess configure {$target} with value {$value} !");
        }else{
            $player->sendMessage("§cFailed configure {$target} with value {$value} !");
        }
    }

    private function getTarget(int $type) : string{
        return match($type){
            self::TYPE_ATTACKDELAY => "AttackDelay",
            self::TYPE_KNOCKBACK => "KnockBack",
            default => throw new \InvalidArgumentException("Unknown type target type: " . $type)
        };
    }

    private function getValue(string $stringValue) : false|float|int{
        if(filter_var($stringValue, FILTER_VALIDATE_FLOAT) !== false){
            return (float) $stringValue;
        }elseif(filter_var($stringValue, FILTER_VALIDATE_INT) !== false){
            return (int) $stringValue;
        }
        return false;
    }
}