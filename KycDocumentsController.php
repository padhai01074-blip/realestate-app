<?php
class KycDocumentsController extends AppController
{
  public $uses = array('KycDocument', 'User');
  public $components = array('Session', 'Upload');

  public function beforeFilter() {
    parent::beforeFilter();
    $this->Auth->allow('upload', 'view');
  }

  public function index() {
    $userId = $this->Session->read('frontUser.User.id');
    if (!$userId) {
      $this->redirect(array('controller' => 'users', 'action' => 'login'));
    }

    $documents = $this->KycDocument->find('all', array(
      'conditions' => array('KycDocument.user_id' => $userId),
      'order' => array('KycDocument.created' => 'DESC')
    ));

    $this->set('documents', $documents);
    $this->set('documentTypes', $this->KycDocument->documentTypes);
  }

  public function upload() {
    $userId = $this->Session->read('frontUser.User.id');
    if (!$userId) {
      $this->redirect(array('controller' => 'users', 'action' => 'login'));
    }

    if ($this->request->is('post')) {
      $this->KycDocument->create();

      // Handle file upload
      if (!empty($this->request->data['KycDocument']['document']['name'])) {
        $file = $this->request->data['KycDocument']['document'];

        // Validate file type
        $allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'application/pdf');
        if (!in_array($file['type'], $allowedTypes)) {
          $this->Session->setFlash(__('Invalid file type. Only JPG, PNG, GIF, and PDF files are allowed.'), 'error');
          return;
        }

        // Validate file size (5MB max)
        if ($file['size'] > 5242880) {
          $this->Session->setFlash(__('File size too large. Maximum 5MB allowed.'), 'error');
          return;
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'kyc_' . $userId . '_' . time() . '.' . $extension;
        $uploadPath = WWW_ROOT . 'files' . DS . 'kyc' . DS;

        // Create directory if it doesn't exist
        if (!file_exists($uploadPath)) {
          mkdir($uploadPath, 0755, true);
        }

        $destination = $uploadPath . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
          $this->request->data['KycDocument']['document_path'] = 'files/kyc/' . $filename;
          $this->request->data['KycDocument']['user_id'] = $userId;
          $this->request->data['KycDocument']['status'] = 'pending';
          $this->request->data['KycDocument']['uploaded_at'] = date('Y-m-d H:i:s');

          if ($this->KycDocument->save($this->request->data)) {
            // Update user's KYC status
            $this->User->id = $userId;
            $this->User->saveField('kyc_status', 'submitted');

            $this->Session->setFlash(__('Document uploaded successfully. It will be reviewed by our team.'), 'success');
            $this->redirect(array('action' => 'index'));
          } else {
            // Delete uploaded file if save failed
            unlink($destination);
            $this->Session->setFlash(__('Failed to save document information.'), 'error');
          }
        } else {
          $this->Session->setFlash(__('Failed to upload file.'), 'error');
        }
      } else {
        $this->Session->setFlash(__('Please select a file to upload.'), 'error');
      }
    }

    $this->set('documentTypes', $this->KycDocument->documentTypes);
  }

  public function view($id = null) {
    $userId = $this->Session->read('frontUser.User.id');
    if (!$userId) {
      $this->redirect(array('controller' => 'users', 'action' => 'login'));
    }

    $document = $this->KycDocument->find('first', array(
      'conditions' => array(
        'KycDocument.id' => $id,
        'KycDocument.user_id' => $userId
      )
    ));

    if (!$document) {
      $this->Session->setFlash(__('Document not found.'), 'error');
      $this->redirect(array('action' => 'index'));
    }

    $this->set('document', $document);
  }

  public function delete($id = null) {
    $userId = $this->Session->read('frontUser.User.id');
    if (!$userId) {
      $this->redirect(array('controller' => 'users', 'action' => 'login'));
    }

    $document = $this->KycDocument->find('first', array(
      'conditions' => array(
        'KycDocument.id' => $id,
        'KycDocument.user_id' => $userId
      )
    ));

    if (!$document) {
      $this->Session->setFlash(__('Document not found.'), 'error');
      $this->redirect(array('action' => 'index'));
    }

    if ($this->KycDocument->delete($id)) {
      // Delete physical file
      $filePath = WWW_ROOT . $document['KycDocument']['document_path'];
      if (file_exists($filePath)) {
        unlink($filePath);
      }

      $this->Session->setFlash(__('Document deleted successfully.'), 'success');
    } else {
      $this->Session->setFlash(__('Failed to delete document.'), 'error');
    }

    $this->redirect(array('action' => 'index'));
  }

  // Admin methods for document review
  public function admin_index() {
    $this->layout = 'admin';

    $conditions = array();
    if (!empty($this->request->data['KycDocument']['status'])) {
      $conditions['KycDocument.status'] = $this->request->data['KycDocument']['status'];
    }

    $this->paginate = array(
      'conditions' => $conditions,
      'contain' => array('User'),
      'order' => array('KycDocument.uploaded_at' => 'DESC'),
      'limit' => 20
    );

    $documents = $this->paginate('KycDocument');
    $this->set('documents', $documents);
  }

  public function admin_review($id = null) {
    $this->layout = 'admin';

    if (!$id) {
      $this->Session->setFlash(__('Invalid document ID.'), 'error');
      $this->redirect(array('action' => 'admin_index'));
    }

    $document = $this->KycDocument->find('first', array(
      'conditions' => array('KycDocument.id' => $id),
      'contain' => array('User')
    ));

    if (!$document) {
      $this->Session->setFlash(__('Document not found.'), 'error');
      $this->redirect(array('action' => 'admin_index'));
    }

    if ($this->request->is('post')) {
      $status = $this->request->data['KycDocument']['status'];
      $notes = $this->request->data['KycDocument']['notes'];

      $this->KycDocument->id = $id;
      $updateData = array(
        'status' => $status,
        'reviewed_at' => date('Y-m-d H:i:s'),
        'reviewer_id' => $this->Session->read('User.id'),
        'notes' => $notes
      );

      if ($this->KycDocument->save($updateData)) {
        // Update user's KYC status
        $this->User->id = $document['User']['id'];
        $this->User->saveField('kyc_status', $status);

        $this->Session->setFlash(__('Document review completed.'), 'success');
        $this->redirect(array('action' => 'admin_index'));
      } else {
        $this->Session->setFlash(__('Failed to update document status.'), 'error');
      }
    }

    $this->set('document', $document);
  }
}
?>
