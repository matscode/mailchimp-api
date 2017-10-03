<?php

/**
 *
 * This Class definition is strictly for MailChimp List management with higher level of abtraction.
 *
 * @package        MailChimp
 * @author         Michael Akanji <matscode@gmail.com>
 * @date           2017-10-01
 *
 */
namespace DrewM\MailChimp;

class MList extends MailChimp
{

    private
        $_knownStatuses =
        [
            'subscribed',
            'unsubscribed',
            'pending',
            'cleaned'
        ],
        $_listId = '';

    public
        $listsResouceFormat = 'lists/%s/members', // format listId into string
        $listsResouceEndpoint = '';

    public function __construct( $api_key )
    {
        parent::__construct( $api_key );
        // make sure all response are formatted as Object
        $this->setFormattedResponseInArray( false );
    }

    /**
     * Method to set the list id this wrapper should communicate with
     *
     * @param $_listId ID of the Mailchimp List
     *
     * @return $this
     * @throws \Exception
     */
    public function setListId( $_listId )
    {
        if ( strlen( $_listId ) == 10 || preg_match( '/^([a-z0-9]+)$/', $_listId ) ) { /// only match lower case List IDs..
            $this->_listId              = $_listId;
            $this->listsResouceEndpoint = sprintf( $this->listsResouceFormat, $this->_listId );

            return $this;
        }

        throw new \Exception( "Invalid List ID :(..." );
    }

    /**
     * returns current ListID
     * @return string
     */
    private function getListId()
    {
        return $this->_listId;
    }

    /**
     * Make sure _listId property is not empty
     *
     * @return bool
     * @throws \Exception
     */
    protected function ListIdNotEmpty()
    {
        if ( $this->getListId() ) {
            return true;
        }

        throw new \Exception( "List ID not set :(..., Set List ID with setListId()" );
    }

    /**
     * Fetch existing member data from mailchimp return false if member does not exist
     *
     * @param $email
     *
     * @return object|false
     */
    public function getMember( $email )
    {
        // make sure listId is set
        $this->ListIdNotEmpty(); // throws an exception on empty

        // get $email hash
        $emailHash = $this->subscriberHash( $email );

        // go on and get list member's status
        $response = $this->get( "lists/{$this->getListId()}/members/{$emailHash}" );
        if ( $response ) {
            return $response;
        }

        return false;
    }

    /**
     * Gets existing members status, returns false if member does not exist
     *
     * @param $email
     *
     * @return bool|string
     */
    public function memberStatus( $email )
    {
        $response = $this->getMember( $email );

        if ( $response && ( $response->status != 404 && in_array( $response->status, $this->_knownStatuses ) ) ) {
            // members exists, return status string
            return $response->status;
        }

        // member does not exist
        return false;
    }


    /**
     * Add a new email to the mailchimp list
     *
     * @param             $email
     * @param string      $status
     * @param string      $lastName
     * @param string      $firstName
     * @param array       $others Incase you want to add more member data see {@link https://api.mailchimp.com/schema/3.0/Lists/Members/Instance.json}
     *                            for member data schema
     *
     * @return bool
     */
    public function addMember( $email, $status = 'subscribed', $lastName = '', $firstName = '', $others = [] )
    {
        // make sure listId is set
        $this->ListIdNotEmpty(); // throws an exception on empty

        $memberData =
            [
                'email_address' => $email,
                'merge_fields'  =>
                    [
                        'FNAME' => $firstName ? $firstName : '',
                        'LNAME' => $lastName ? $lastName : ''
                    ],
                'status'        => $status,
            ];

        if ( count( $others ) > 0 ) {
            // do a merge
            $memberData = array_merge( $memberData, $others );
        }

        $response = $this->post( "lists/{$this->getListId()}/members", $memberData );

        if ( $response && isset( $response->email_address ) ) {
            return $this->memberStatus( $response->email_address );
        }

        return false;
    }

    /**
     * Update a list member data
     *
     * @param             $email
     * @param             $status
     * @param string      $lastName
     * @param string      $firstName
     * @param array       $others Incase you want to add more member data see {@link https://api.mailchimp.com/schema/3.0/Lists/Members/Instance.json}
     *                            for member data schema
     *
     * @return object|bool
     */
    public function updateMember( $email, $status, $lastName = '', $firstName = '', $others = [] )
    {
        $this->ListIdNotEmpty(); // throw an exception on empty

        // get existing user data
        if ( ! $this->memberStatus( $email ) ) {
            return false;
        }
        $memberData = $this->getMember( $email );

        $memberUpdateData = [
            'email_address' => $memberData->email_address,
            'merge_fields'  =>
                [
                    'FNAME' => $firstName ? $firstName : $memberData->merge_fields->FNAME,
                    'LNAME' => $lastName ? $lastName : $memberData->merge_fields->LNAME
                ],
            'status'        => $status ? $status : $memberData->status,
        ];

        if ( count( $others ) > 0 ) {
            // do a merge
            $memberUpdateData = array_merge( $memberUpdateData, $others );
        }

        $response = $this->patch( "lists/{$this->getListId()}/members/{$memberData->id}", $memberUpdateData );

        return $response;

    }

    /**
     * Soft Deletes(archives) an exiting member from a list
     *
     * @param      $email
     * @param bool $hardDelete Permanently delete a member if sets to true
     */
    public function deleteMember( $email, $hardDelete = false )
    {
        // archiving a member == soft delete

        if ( $hardDelete ) {
            $this->ListIdNotEmpty(); // throw an exception on empty
            // Permanent/hard delete by making a DELETE call
            $this->delete( "lists/{$this->getListId()}/members/{$this->subscriberHash($email)}" );
        }

        // do the soft delete
        $this->updateMember( $email, 'cleaned' );

        // Mailchimp returns null, so this will return void
        return;
    }

    /**
     * Sets a member to be unsubscribed from list
     *
     * @param $email
     *
     * @return array|bool|false
     */
    public function unsubscribeMember( $email )
    {
        return $this->updateMember( $email, 'unsubscribed' );
    }

}
