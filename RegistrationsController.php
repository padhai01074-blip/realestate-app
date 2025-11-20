<?php
class RegistrationsController extends AppController
{
  public $uses = array('User', 'KycDocument');
  public $components = array('Session', 'Email', 'Captcha');

  public function beforeFilter() {
    parent::beforeFilter();
    $this->Auth->allow('index', 'register', 'verify_email', 'verify_otp');
  }

  public function index() {
    // Display registration form
  }

  public function register() {
    if ($this->request->is('post')) {
      $this->User->create();

      // Set default role as buyer if not specified
      if (empty($this->request->data['User']['role'])) {
        $this->request->data['User']['role'] = 'buyer';
      }

      // Set verification status
      $this->request->data['User']['verification_status'] = 'pending';

      // Generate OTP for email verification
      $otp = rand(100000, 999999);
      $this->request->data['User']['email_otp'] = $otp;
      $this->request->data['User']['otp_expiry'] = date('Y-m-d H:i:s', strtotime('+24 hours'));

      if ($this->User->save($this->request->data)) {
        // Send verification email
        $this->_sendVerificationEmail($this->User->id, $otp);

        $this->Session->setFlash(__('Registration successful! Please check your email for verification code.'), 'success');
        $this->redirect(array('action' => 'verify_email', $this->User->id));
      } else {
        $this->Session->setFlash(__('Registration failed. Please try again.'), 'error');
      }
    }

    // Set roles for form
    $this->set('roles', $this->User->roles);
  }

  public function verify_email($userId = null) {
    if (!$userId) {
      $this->redirect(array('action' => 'index'));
    }

    $user = $this->User->findById($userId);
    if (!$user) {
      $this->Session->setFlash(__('Invalid user.'), 'error');
      $this->redirect(array('action' => 'index'));
    }

    if ($this->request->is('post')) {
      $otp = $this->request->data['User']['otp'];

      if ($user['User']['email_otp'] == $otp && strtotime($user['User']['otp_expiry']) > time()) {
        // Update verification status
        $this->User->id = $userId;
        $this->User->saveField('verification_status', 'verified');
        $this->User->saveField('email_verified', 1);

        $this->Session->setFlash(__('Email verified successfully! You can now login.'), 'success');
        $this->redirect(array('controller' => 'users', 'action' => 'login'));
      } else {
        $this->Session->setFlash(__('Invalid or expired OTP.'), 'error');
      }
    }

    $this->set('userId', $userId);
  }

  private function _sendVerificationEmail($userId, $otp) {
    $user = $this->User->findById($userId);

    $this->Email->to = $user['User']['email'];
    $this->Email->subject = 'Email Verification - ' . Configure::read('site_name');
    $this->Email->from = Configure::read('email_from');

    $message = "Dear " . $user['User']['name'] . ",\n\n";
    $message .= "Your verification code is: " . $otp . "\n\n";
    $message .= "Please enter this code to verify your email address.\n\n";
    $message .= "Best regards,\n" . Configure::read('site_name');

    $this->Email->send($message);
  }
}
?>
