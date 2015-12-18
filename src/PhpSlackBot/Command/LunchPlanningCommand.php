<?php
namespace PhpSlackBot\Command;

class LunchPlanningCommand extends BaseCommand {

    protected $restaurantFilePath =  '/restaurants.txt';
    protected $restaurants = [];
    private $count = 0;
    private $initiator;
    private $scores = array();
    private $status = 'free';

    protected function configure() {
        $this->setName('lunch');
    }

    protected function execute($message, $context) {
        $args = $this->getArgs($message);
        $command = isset($args[1]) ? $args[1] : '';

        switch ($command) {
        case 'start':
            $this->start($args);
            break;
        case 'status':
            $this->status();
            break;
        case 'vote':
            $this->vote($args);
            break;
        case 'add':
            $this->addRestaurant($args);
            break;
        case 'end':
            $this->end();
            break;
        default:
            $this->send($this->getCurrentChannel(), $this->getCurrentUser(),
                        'No comprendo. Use "'.$this->getName().' start" or "'.$this->getName().' status"');
        }
    }

    private function start($args) {
        if ($this->status == 'free') {
            $this->subject = isset($args[2]) ? $args[2] : null;
            if (!is_null($this->subject)) {
                $this->subject = str_replace(array('<', '>'), '', $this->subject);
            }
            $this->status = 'running';
            $this->initiator = $this->getCurrentUser();
            $this->restaurants = [];
            $restaurants = explode("\n", file_get_contents(__DIR__  . $this->restaurantFilePath));
            foreach($restaurants as $restaurant) {
                if(trim($restaurant) == '') {
                    continue;
                }
                $this->restaurants[] = $restaurant;
            }

            $this->scores = [];

            $message = 'Lunch voting start by '.$this->getUsernameFromUserId($this->initiator)."\n".
                'You can vote on the following restaurants: ' . "\n";

            foreach($this->restaurants as $key => $restaurant) {
                $message .= $key . ' - ' . $restaurant . "\n";
            }

            $message .= 'Please vote by "@butler vote [number]" entering a number stated before the place you would like to go' . "\n";
            $this->send($this->getCurrentChannel(), null, $message);
            $this->send($this->getCurrentChannel(), $this->getCurrentUser(), 'Use "'.$this->getName().' end" to end the session');
        }
        else {
            $this->send($this->getCurrentChannel(), $this->getCurrentUser(), 'A lunch session is still active');
        }
    }

    private function status() {
        $message = 'Current status : '.$this->status;
        if ($this->status == 'running') {
            $message .= "\n".'Initiator : '.$this->getUsernameFromUserId($this->initiator);
        }
        $this->send($this->getCurrentChannel(), null, $message);
        if ($this->status == 'running') {
            if (empty($this->scores)) {
                $this->send($this->getCurrentChannel(), null, 'No one has voted yet');
            }
            else {
                $message = '';
                foreach ($this->scores as $user => $score) {
                    $message .= $this->getUsernameFromUserId($user).' has voted'."\n";
                }
                $this->send($this->getCurrentChannel(), null, $message);
            }
        }
    }

    private function vote($args) {
        if ($this->status == 'running') {
            $score = isset($args[2]) ? $args[2] : 0;

            $sequence = array_keys($this->restaurants);
            if (!in_array($score, $sequence)) {
                $this->send($this->getCurrentChannel(), $this->getCurrentUser(), 'Use "'.$this->getName().' vote [number]". Choose [number] out of '.implode(', ', array_keys($this->restaurants)));
            }
            else {
                $this->scores[$this->getCurrentUser()] = (int) $score;
                $this->send($this->getCurrentChannel(), $this->getCurrentUser(),
                            'Thank you! Your vote ('.$score.') has been recorded You can still change your vote until the end of the session');
            }
        }
        else {
            $this->send($this->getCurrentChannel(), $this->getCurrentUser(), 'There is no lunch session. You can start one with "'.$this->getName().' start"');
        }
    }

    private function end() {
        if ($this->status == 'running') {
            if ($this->getCurrentUser() == $this->initiator) {
                $message = 'Ending session'.(!is_null($this->subject) ? ' for '.$this->subject : '')."\n".'Results : '."\n";
                if (empty($this->scores)) {
                    $message .= 'No vote !';
                }
                else {
                    foreach ($this->scores as $user => $score) {
                        $message .= $this->getUsernameFromUserId($user).' => '.$this->restaurants[$score]."\n";
                    }
                    $message .= '------------------'."\n";
                    $message .= 'Based on the voting you should go to: ' . $this->restaurants[$this->getWinner()];
                }
                $this->send($this->getCurrentChannel(), null, $message);
                $this->status = 'free';
            }
            else {
                $this->send($this->getCurrentChannel(), $this->getCurrentUser(), 'Only '.$this->getUsernameFromUserId($this->initiator).' can end the session');
            }
        }
        else {
            $this->send($this->getCurrentChannel(), $this->getCurrentUser(), 'There is no lunch session. You can start one with "'.$this->getName().' start"');
        }
    }

    private function getArgs($message) {
        $args = array();
        if (isset($message['text'])) {
            $args = array_values(array_filter(explode(' ', $message['text'])));
        }
        $commandName = $this->getName();
        // Remove args which are before the command name
        $finalArgs = array();
        $remove = true;
        foreach ($args as $arg) {
            if ($commandName == $arg) {
                $remove = false;
            }
            if (!$remove) {
                $finalArgs[] = $arg;
            }
        }
        return $finalArgs;
    }

    private function getWinner() {
        $score = [];

        foreach($this->scores as $restaurantScoreKey) {
            foreach($this->restaurants as $key => $restaurant) {
                if($restaurantScoreKey == $key) {
                    if(!isset($score[$key])) {
                        $score[$key] = 0;
                    }

                    $score[$key]++;
                }
            }
        }

        asort($score);

        $scoreKeys = array_keys($score);
        return end($scoreKeys);
    }

    private function addRestaurant($args)
    {
        $restaurant = '';
        for($i=2;$i<=count($args);$i++) {
            $restaurant.= ' ' . $args[$i];
        }

        file_put_contents(__DIR__ . $this->restaurantFilePath, trim($restaurant) . "\n", FILE_APPEND);
        $this->send($this->getCurrentChannel(), $this->getCurrentUser(), 'Thanks "' . $restaurant . '" is added, it will be used in the next vote!');
    }

}