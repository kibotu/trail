<?php

declare(strict_types=1);

namespace Trail\Services;

use Trail\Models\User;

class MentionService
{
    private User $userModel;
    
    public function __construct(User $userModel)
    {
        $this->userModel = $userModel;
    }
    
    /**
     * Extract @mentions from text
     * Returns array of user IDs to notify
     */
    public function extractMentions(string $text): array
    {
        // Match @username (alphanumeric + underscore)
        preg_match_all('/@(\w+)/', $text, $matches);
        $nicknames = array_unique($matches[1]);
        
        $userIds = [];
        foreach ($nicknames as $nickname) {
            $user = $this->userModel->findByNickname($nickname);
            if ($user) {
                $userIds[] = (int) $user['id'];
            }
        }
        
        return array_unique($userIds);
    }
    
    /**
     * Get mentioned users with their details
     * Returns array of user arrays
     */
    public function getMentionedUsers(string $text): array
    {
        preg_match_all('/@(\w+)/', $text, $matches);
        $nicknames = array_unique($matches[1]);
        
        $users = [];
        foreach ($nicknames as $nickname) {
            $user = $this->userModel->findByNickname($nickname);
            if ($user) {
                $users[] = $user;
            }
        }
        
        return $users;
    }
}
