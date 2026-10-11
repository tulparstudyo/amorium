<?php namespace Amorium\Form\Controllers;

use Flash;
use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Amorium\Form\Models\Submission;

/**
 * Submissions lists the posted forms and shows the details of each one.
 */
class Submissions extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];

    public $formConfig = 'config_form.yaml';

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['amorium.form.submissions'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('October.Tailor', 'entry_amorium-page', 'submissions');
    }

    public function preview($id)
    {
        $result = $this->asExtension('FormController')->preview($id);

        if ($this->formGetModel()) {
            $this->formGetModel()->markAsRead();
        }

        return $result;
    }

    public function index_onDelete()
    {
        $checkedIds = post('checked');

        if (!is_array($checkedIds) || !$checkedIds) {
            Flash::error(__("There are no selected records."));
            return $this->listRefresh();
        }

        Submission::whereIn('id', $checkedIds)->get()->each->delete();
        Flash::success(__("The selected records have been deleted."));

        return $this->listRefresh();
    }

    public function preview_onDelete($id)
    {
        if ($submission = Submission::find($id)) {
            $submission->delete();
            Flash::success(__("The submission has been deleted."));
        }

        return Backend::redirect('amorium/form/submissions');
    }
}
