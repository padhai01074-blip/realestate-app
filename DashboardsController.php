<?php
App::uses('CakeTime', 'Utility');
App::uses('CakeNumber', 'Utility');
class DashboardsController extends AppController
{
    public function beforeFilter()
    {
        parent::beforeFilter();
        $this->authenticate();
        $this->userId=$this->userValue['User']['id'];
    }
    public function index()
    {
        $this->loadModel('DealsPayment');
        $currentDateTime=CakeTime::format('Y-m-d',CakeTime::convert(time(),$this->siteTimezone));

        $dealsPayment=$this->DealsPayment->find('all',array('fields'=>array('DealsPayment.*','Paymenttype.name'),
                                                            'joins'=>array(array('table'=>'paymenttypes','alias'=>'Paymenttype','type'=>'INNER','conditions'=>array('DealsPayment.paymenttype_id=Paymenttype.id')),
                                                                           array('table'=>'deals','alias'=>'Deal','type'=>'INNER','conditions'=>array('DealsPayment.deal_id=Deal.id'))),
                                                            'conditions'=>array('Deal.client_id'=>$this->userId),
                                                            'order'=>array('id'=>'desc'),
                                                            'limit'=>5));
        $this->set('dealsPayment',$dealsPayment);

        // Add KYC status for dashboard display
        $user = $this->User->find('first', array(
            'conditions' => array('User.id' => $this->userId),
            'fields' => array('role', 'kyc_status', 'verification_status')
        ));
        $this->set('userRole', $user['User']['role']);
        $this->set('kycStatus', $user['User']['kyc_status']);
        $this->set('verificationStatus', $user['User']['verification_status']);
    }

    public function agent()
    {
        // Agent-specific dashboard
        $this->set('userRole', 'agent');

        // Load agent-specific data (properties, clients, etc.)
        // This can be expanded based on requirements
        $user = $this->User->find('first', array(
            'conditions' => array('User.id' => $this->userId),
            'fields' => array('kyc_status', 'verification_status', 'company_name', 'specialization')
        ));
        $this->set('kycStatus', $user['User']['kyc_status']);
        $this->set('verificationStatus', $user['User']['verification_status']);
        $this->set('companyName', $user['User']['company_name']);
        $this->set('specialization', $user['User']['specialization']);
    }

    public function admin()
    {
        // Admin dashboard with system overview
        $this->set('userRole', 'admin');

        // Load admin-specific data (user stats, pending KYC, etc.)
        $totalUsers = $this->User->find('count');
        $pendingKyc = $this->User->find('count', array(
            'conditions' => array('User.kyc_status' => 'submitted')
        ));
        $unverifiedUsers = $this->User->find('count', array(
            'conditions' => array('User.verification_status' => 'pending')
        ));

        $this->set('totalUsers', $totalUsers);
        $this->set('pendingKyc', $pendingKyc);
        $this->set('unverifiedUsers', $unverifiedUsers);
    }
}