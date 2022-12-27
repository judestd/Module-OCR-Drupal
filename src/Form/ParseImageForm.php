<?php

namespace Drupal\module_ocr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
/**
 * Implements an example form.
 */
class ParseImageForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'parse_image_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['upload']['image_dir'] = [
            '#type'                 => 'managed_file',
            '#upload_location'      => 'public://images/',
            '#multiple'             => false,
            '#description'          => $this->t('Allowed extensions: png jpg jpeg'),
            '#upload_validators'    => [
                'file_validate_is_image'   => [],
                'file_validate_extensions' => ['png jpg jpeg'],
                'file_validate_size'       => [25600000]
            ],
            '#title'                => $this->t('Upload an image file to parse to text')
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Parse image to text'),
            '#button_type' => 'primary',
        ];
        
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $file = $form_state->getValue(['upload' => 'image_dir']);
        if (!$file) {
            $form_state->setErrorByName('upload', $this->t('Please upload image'));
        }
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        try {
            $file_data = $form_state->getValue(['upload' => 'image_dir']);
            $file = \Drupal\file\Entity\File::load($file_data[0]);
            $file_name = $file->getFilename();
            $cFile = curl_file_create("public://images/". $file_name);
    
            $url_endpoint = 'https://api.ocr.space/parse/image';
            $post_body = [
                'file' => $cFile,
                'apikey' => 'd8daf238cb88957'
            ];
            $headers = [
                'Content-Type: multipart/form-data',
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url_endpoint);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_body);
    
            $result = curl_exec($ch);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $body = substr($result, $header_size);
    
            curl_close($ch);
    
            $texts_array = json_decode($body, true);
            $text_string = $texts_array['ParsedResults'][0]['ParsedText'];
            
            $file->delete();
    
            $this->messenger()->addStatus($this->t("The text in image is: @text", ['@text' => $text_string]));
        } catch (\Exception $e) {
            $file->delete();
            $this->messenger()->addStatus($this->t('Could not extract text. Please check your image and try again.'));
        }
    }
}