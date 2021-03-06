<?php defined('SYSPATH') or die('No direct access allowed.');

class Location extends Bluebox_Record
{
    public static $errors = array (
        'name' => array (
            'required' => 'Location name is required',
        ),
        'domain' => array (
            'required' => 'Domain is required',
            'unique' => 'This domain already exists',
            'chars' => 'Only letters, numbers, dot, and hyphen'
        )
    );

    /**
     * Sets the table name, and defines table columns.
     */
    public function setTableDefinition()
    {
        // COLUMN DEFINITIONS
        $this->hasColumn('location_id', 'integer', 11, array('unsigned' => true, 'notnull' => true, 'primary' => true, 'autoincrement' => true));
        $this->hasColumn('account_id', 'integer', 11, array('unsigned' => true, 'notnull' => true));
        $this->hasColumn('name', 'string', 100, array('notnull' => true, 'notblank' => true));
        $this->hasColumn('domain', 'string', 100, array('unique' => true, 'notnull' => true, 'notblank' => true));
    }

    /**
     * Sets up relationships, behaviors, etc.
     */
    public function setUp()
    {
        // RELATIONSHIPS
        $this->hasOne('Account', array('local' => 'account_id', 'foreign' => 'account_id'));
        $this->hasMany('Number', array('local' => 'location_id', 'foreign' => 'location_id', 'cascade' => array('delete')));
        $this->hasMany('User', array('local' => 'location_id', 'foreign' => 'location_id'));

        // BEHAVIORS
        $this->actAs('GenericStructure');
        $this->actAs('Timestampable');
        $this->actAs('TelephonyEnabled');
        $this->actAs('MultiTenant');
    }

    public function preDelete(Doctrine_Event $event)
    {
        $unlimit = Session::instance()->get('bluebox.delete.unlimit', FALSE);

        if (!$unlimit AND count($this->getTable()->findAll()) <= 1)
	    {
	        throw new Exception ('You can not delete the only location for this account');
    	}
    }

    public function preValidate(Doctrine_Event $event)
    {
        $record = &$event->getInvoker();

        if (empty($record['domain']) || !preg_match('/^[0-9a-zA-Z\.-]*$/D', $record['domain']))
        {
            $this->getErrorStack()->add('domain', 'chars');
        }
    }

    public static function dictionary($multitenancy = TRUE)
    {
        $locations = array();

        if (!$multitenancy)
        {
            Doctrine::getTable('Location')->getRecordListener()->get('MultiTenant')->setOption('disabled', TRUE);

            $q = Doctrine_Query::create()
                ->from('Location l, l.Account a')
                ->select('l.location_id, l.name, a.name');

            $results = $q->fetchArray();

            Doctrine::getTable('Location')->getRecordListener()->get('MultiTenant')->setOption('disabled', FALSE);
        }
        else
        {
            $q = Doctrine_Query::create()
                ->from('Location l')
                ->select('l.location_id, l.name');

            $results = $q->fetchArray();
        }

        foreach($results as $result)
        {
            if (!empty($result['Account']['name']))
            {
                $locations[$result['Account']['name']][$result['location_id']] = $result['name'];
            }
            else
            {
                $locations[$result['location_id']] = $result['name'];
            }
        }

        return $locations;
    }
}
