<?php

/**
 * Forum Subscription: Allows members to subscribe to a Forum
 * and receive email notifications when forums are modified.
 *
 * @package forum
 */

class ForumSubscription extends DataObject {
	
	private static $db = array(
		"LastSent" => "SS_Datetime"
	);

	private static $has_one = array(
		"Forum" => "Forum",
		"Member" => "Member"
	);
	
	
	/**
	 * Checks to see if a Member is already subscribed to this forum
	 *
	 * @param int $ForumID The ID of the thread to check
	 * @param int $MemberID The ID of the currently logged in member (Defaults to Member::currentUserID())
	 *
	 * @return bool true if they are subscribed, false if they're not
	 * @TODO unit test for this to check if a given known member subscription status can be determined using this method. is/isn't subscribed.
	 * @TODO move this to the Forum class and refactor?
	 */
	static function already_subscribed($ForumID, $MemberID = null) {
		if(!$MemberID) $MemberID = Member::currentUserID();

		// @TODO we may not need this as ORM escapes SQL inputs for us. But will need to check.
		$SQL_ForumID = Convert::raw2sql($ForumID);
		$SQL_MemberID = Convert::raw2sql($MemberID);
		//@TODO may not need with check as ORM will simply return a false if when using these no objects are returned.
		if($SQL_ForumID=='' || $SQL_MemberID=='')
			return false;

		$checkSubscribed = ForumSubscription::get()
			->filter(array(
				'ForumID' => $SQL_ForumID,
				'MemberID' => $SQL_MemberID
			))
			->count();

		if($checkSubscribed > 0){
			//subscribed to this forum
			return true;
		} else {
			//not subscribed to this forum
			return false;
		}
	}
	
	/**
	 * Notifies everybody that has subscribed to this forum that a new post has been added.
	 * To get emailed, people subscribed to this Forum must have visited the forum 
	 * since the last time they received an email
	 *
	 * @TODO Move to Forum and refactor? TO be discussed and explored.
	 * @param Post $post The post that has just been added
	 */
	static function notify(Post $post) {		
		// Use email address specified in the CMS
		// This might not work when there's multiple forums as i'm not specifying the ID of the forum
		// Yet to test
		//@TODO moving this method to Forum class would mean you could use on multiple ForumHolders by referencing the parent.
		$emailAddress = ForumHolder::get()->first()->ForumEmailAddress;

		//@TODO explain in docblock what this is doing?
		$list = ForumSubscription::get()
			->filter(array(
				'ForumID'=>$post->ForumID,
				'MemberID:not' => $post->AuthorID
			));
			
		if($list) {
			foreach($list as $obj) {
				//@TODO may not need to escape if we now use the ORM which does this for us, something to check?
				$SQL_id = Convert::raw2sql((int)$obj->MemberID);

				// Get the members details
				$member = Member::get()->filter(array('ID',$SQL_id))->first();
				
				if($member) {
					$email = new Email();
					$email->setFrom($emailAddress);
					$email->setTo($member->Email);
					$email->setSubject('New Post in the forum ' . $post->Thread()->Forum()->Title);
					$email->setTemplate('ForumMember_ForumNotification');
					$email->populateTemplate($member);
					$email->populateTemplate($post);
					$email->populateTemplate(array(
						'UnsubscribeLink' => $post->Thread()->Forum()->Link() . '/forumUnsubscribe/?BackURL=' . $post->Thread()->Forum()->Link()						
					));
					$email->send();
				}
			}
		}
	}
}
