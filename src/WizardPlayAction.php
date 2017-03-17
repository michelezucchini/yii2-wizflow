<?php
namespace raoul2000\wizflow;

use Yii;
use yii\base\Model;
use yii\helpers\BaseFileHelper;
use yii\validators\FileValidator;
use yii\web\UploadedFile;

class WizardPlayAction extends \yii\base\Action
{
    public $wizardManagerName = 'wizflowManager';
    public $url = ['wizflow'];
    public $urlFinish = ['finish'];

    /**
     * @param string $nav
     * @return string
     */
    public function run($nav = '')
    {
        $wizard = Yii::$app->get($this->wizardManagerName);

        if ($nav == 'prev') {
            $model = $wizard->getPreviousStep();
            if ($model == null) {
                $this->redirect(array_merge($this->url, ['nav' => 'start']));
            }
        } elseif ($nav == 'start') {
            $model = $wizard->start();
        } else {
            /** @var Model $model */
            $model = $wizard->getCurrentStep();
            if ($model == null) {
                $this->controller->redirect($this->urlFinish);
            } else {

                $oldModel = $model;
                if ($model->load(Yii::$app->request->post())) {

                    $validators = $model->validators;
                    /** @var FileValidator FileValidator */
                    foreach ($validators as $validator) {
                        if (is_a($validator, FileValidator::className(), true)) {
                            foreach ($validator->attributes as $attribute) {
                                $uploadedFile = UploadedFile::getInstance($model, $attribute);
                                if (empty($uploadedFile)) {
                                    $model->$attribute = $oldModel->$attribute;
                                } else {
                                    $model->$attribute = $this->saveTmpfile($uploadedFile);
                                }
                            }

                        }
                    }
                    if ($model->validate()) {
                        // current step has been completed : save it and get next step
                        $wizard->updateCurrentStep($model);
                        $model = $wizard->getNextStep();
                        if ($model == null) {
                            //we reached the last step : save it and go tp the finish page
                            $wizard->save();
                            $this->controller->redirect($this->urlFinish);
                        }
                    }
                } else {
                    if (empty($model)){
                        $model = $wizard->getNextStep();
                        if ($model == null) {
                            $this->controller->redirect($this->urlFinish);
                        }
                    }
                }

            }
        }
        if ($model != null) {
            $viewname = $model->getWorkflowStatus()->getMetadata('view');
            $wizard->save();
            return $this->controller->render($viewname, [
                'model' => $model,
                'path' => $wizard->getPath()
            ]);
        }
    }

    /**
     * Store the file in a secure position
     *
     * @param UploadedFile $uploadedFile
     * @return UploadedFile
     */
    private function saveTmpfile($uploadedFile)
    {
        $storePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . 'wizflow';
        if(!is_dir($storePath)) {
            BaseFileHelper::createDirectory($storePath,775,true);
        }
        $newTempfile = $storePath.DIRECTORY_SEPARATOR. basename($uploadedFile->tempName);
        if(copy($uploadedFile->tempName, $newTempfile)) {
            $uploadedFile->tempName = $newTempfile;
        }
        return $uploadedFile;
    }

}
