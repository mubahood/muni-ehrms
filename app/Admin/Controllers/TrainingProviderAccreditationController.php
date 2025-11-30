<?php

namespace App\Admin\Controllers;

use App\Models\TrainingProviderAccreditation;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class TrainingProviderAccreditationController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'TrainingProviderAccreditation';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new TrainingProviderAccreditation());

        $grid->column('id', __('Id'));
        $grid->column('created_at', __('Created at'));
        $grid->column('updated_at', __('Updated at'));
        $grid->column('user_id', __('User id'));
        $grid->column('application_date', __('Application date'));
        $grid->column('establishment_name', __('Establishment name'));
        $grid->column('street_address', __('Street address'));
        $grid->column('postal_address', __('Postal address'));
        $grid->column('website_address', __('Website address'));
        $grid->column('contact_person', __('Contact person'));
        $grid->column('telephone_number', __('Telephone number'));
        $grid->column('mobile_number', __('Mobile number'));
        $grid->column('email_address', __('Email address'));
        $grid->column('applied_fields', __('Applied fields'));
        $grid->column('applied_levels', __('Applied levels'));
        $grid->column('number_of_registered_trainers', __('Number of registered trainers'));
        $grid->column('number_of_accredited_trainers', __('Number of accredited trainers'));
        $grid->column('official_examination_awarding_body', __('Official examination awarding body'));
        $grid->column('number_of_trainees_male_pt', __('Number of trainees male pt'));
        $grid->column('number_of_trainees_male_ft', __('Number of trainees male ft'));
        $grid->column('number_of_trainees_female_pt', __('Number of trainees female pt'));
        $grid->column('number_of_trainees_female_ft', __('Number of trainees female ft'));
        $grid->column('trainer_name', __('Trainer name'));
        $grid->column('naqaa_accreditation_number', __('Naqaa accreditation number'));
        $grid->column('trainer_qualifications', __('Trainer qualifications'));
        $grid->column('accredited_areas', __('Accredited areas'));
        $grid->column('enrolment_dates', __('Enrolment dates'));
        $grid->column('declaration_principal_ceo_contact_person_name', __('Declaration principal ceo contact person name'));
        $grid->column('declaration_principal_ceo_contact_person_signature', __('Declaration principal ceo contact person signature'));
        $grid->column('declaration_principal_ceo_contact_person_date', __('Declaration principal ceo contact person date'));
        $grid->column('declaration_chairperson_name', __('Declaration chairperson name'));
        $grid->column('declaration_chairperson_signature', __('Declaration chairperson signature'));
        $grid->column('declaration_chairperson_date', __('Declaration chairperson date'));
        $grid->column('submitted_by_name', __('Submitted by name'));
        $grid->column('submitted_by_signature', __('Submitted by signature'));
        $grid->column('submitted_by_date', __('Submitted by date'));
        $grid->column('received_by_name', __('Received by name'));
        $grid->column('received_by_signature', __('Received by signature'));
        $grid->column('received_by_date', __('Received by date'));
        $grid->column('adequate_facilities', __('Adequate facilities'));
        $grid->column('clean_water_supply', __('Clean water supply'));
        $grid->column('adequate_power_supply', __('Adequate power supply'));
        $grid->column('library_resource_centre', __('Library resource centre'));
        $grid->column('staffroom', __('Staffroom'));
        $grid->column('centre_manager_office', __('Centre manager office'));
        $grid->column('student_recreational_waiting_area', __('Student recreational waiting area'));
        $grid->column('sitting_arrangement', __('Sitting arrangement'));
        $grid->column('relevant_teaching_materials', __('Relevant teaching materials'));
        $grid->column('relevant_tools', __('Relevant tools'));
        $grid->column('relevant_equipment', __('Relevant equipment'));
        $grid->column('functional_board_of_directors', __('Functional board of directors'));
        $grid->column('management_structure', __('Management structure'));
        $grid->column('service_rules', __('Service rules'));
        $grid->column('staff_appointment_letters', __('Staff appointment letters'));
        $grid->column('payment_evidence', __('Payment evidence'));
        $grid->column('filing_recording_system', __('Filing recording system'));
        $grid->column('proper_storage', __('Proper storage'));
        $grid->column('adequate_furniture', __('Adequate furniture'));
        $grid->column('registered_accredited_trainers', __('Registered accredited trainers'));
        $grid->column('tutors_teaching_qualification', __('Tutors teaching qualification'));
        $grid->column('teaching_certificate_or_inservice_training', __('Teaching certificate or inservice training'));
        $grid->column('proof_of_experience', __('Proof of experience'));
        $grid->column('volunteers_training', __('Volunteers training'));
        $grid->column('tutors_appraisal_mechanism', __('Tutors appraisal mechanism'));
        $grid->column('training_request_response', __('Training request response'));
        $grid->column('employment_demand_response', __('Employment demand response'));
        $grid->column('curriculum_suitability', __('Curriculum suitability'));
        $grid->column('curriculum_practice_theory_balance', __('Curriculum practice theory balance'));
        $grid->column('practical_sessions', __('Practical sessions'));
        $grid->column('timetable', __('Timetable'));
        $grid->column('lesson_plans', __('Lesson plans'));
        $grid->column('teaching_aids', __('Teaching aids'));
        $grid->column('reference_materials', __('Reference materials'));
        $grid->column('code_of_conduct', __('Code of conduct'));
        $grid->column('guidance_counseling_officer', __('Guidance counseling officer'));
        $grid->column('learner_grievance_procedures', __('Learner grievance procedures'));
        $grid->column('confidentiality_guarantee', __('Confidentiality guarantee'));
        $grid->column('facility_accessibility', __('Facility accessibility'));
        $grid->column('tuition_fee_policy', __('Tuition fee policy'));
        $grid->column('learning_environment', __('Learning environment'));
        $grid->column('entry_requirements', __('Entry requirements'));
        $grid->column('health_safety_policy', __('Health safety policy'));
        $grid->column('health_safety_facilities', __('Health safety facilities'));
        $grid->column('course_syllabus', __('Course syllabus'));
        $grid->column('course_timetable', __('Course timetable'));
        $grid->column('course_reference_materials', __('Course reference materials'));
        $grid->column('entry_requirements_document', __('Entry requirements document'));
        $grid->column('non_refundable_accreditation_fee', __('Non refundable accreditation fee'));
        $grid->column('refundable_accreditation_fee_per_programme', __('Refundable accreditation fee per programme'));
        $grid->column('accreditation_fee_refund_policy', __('Accreditation fee refund policy'));
        $grid->column('status', __('Status'));
        $grid->column('payment_status', __('Payment status'));
        $grid->column('payment_date', __('Payment date'));
        $grid->column('payment_reference', __('Payment reference'));
        $grid->column('payment_details', __('Payment details'));
        $grid->column('payment_amount', __('Payment amount'));
        $grid->column('payment_method', __('Payment method'));

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(TrainingProviderAccreditation::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('user_id', __('User id'));
        $show->field('application_date', __('Application date'));
        $show->field('establishment_name', __('Establishment name'));
        $show->field('street_address', __('Street address'));
        $show->field('postal_address', __('Postal address'));
        $show->field('website_address', __('Website address'));
        $show->field('contact_person', __('Contact person'));
        $show->field('telephone_number', __('Telephone number'));
        $show->field('mobile_number', __('Mobile number'));
        $show->field('email_address', __('Email address'));
        $show->field('applied_fields', __('Applied fields'));
        $show->field('applied_levels', __('Applied levels'));
        $show->field('number_of_registered_trainers', __('Number of registered trainers'));
        $show->field('number_of_accredited_trainers', __('Number of accredited trainers'));
        $show->field('official_examination_awarding_body', __('Official examination awarding body'));
        $show->field('number_of_trainees_male_pt', __('Number of trainees male pt'));
        $show->field('number_of_trainees_male_ft', __('Number of trainees male ft'));
        $show->field('number_of_trainees_female_pt', __('Number of trainees female pt'));
        $show->field('number_of_trainees_female_ft', __('Number of trainees female ft'));
        $show->field('trainer_name', __('Trainer name'));
        $show->field('naqaa_accreditation_number', __('Naqaa accreditation number'));
        $show->field('trainer_qualifications', __('Trainer qualifications'));
        $show->field('accredited_areas', __('Accredited areas'));
        $show->field('enrolment_dates', __('Enrolment dates'));
        $show->field('declaration_principal_ceo_contact_person_name', __('Declaration principal ceo contact person name'));
        $show->field('declaration_principal_ceo_contact_person_signature', __('Declaration principal ceo contact person signature'));
        $show->field('declaration_principal_ceo_contact_person_date', __('Declaration principal ceo contact person date'));
        $show->field('declaration_chairperson_name', __('Declaration chairperson name'));
        $show->field('declaration_chairperson_signature', __('Declaration chairperson signature'));
        $show->field('declaration_chairperson_date', __('Declaration chairperson date'));
        $show->field('submitted_by_name', __('Submitted by name'));
        $show->field('submitted_by_signature', __('Submitted by signature'));
        $show->field('submitted_by_date', __('Submitted by date'));
        $show->field('received_by_name', __('Received by name'));
        $show->field('received_by_signature', __('Received by signature'));
        $show->field('received_by_date', __('Received by date'));
        $show->field('adequate_facilities', __('Adequate facilities'));
        $show->field('clean_water_supply', __('Clean water supply'));
        $show->field('adequate_power_supply', __('Adequate power supply'));
        $show->field('library_resource_centre', __('Library resource centre'));
        $show->field('staffroom', __('Staffroom'));
        $show->field('centre_manager_office', __('Centre manager office'));
        $show->field('student_recreational_waiting_area', __('Student recreational waiting area'));
        $show->field('sitting_arrangement', __('Sitting arrangement'));
        $show->field('relevant_teaching_materials', __('Relevant teaching materials'));
        $show->field('relevant_tools', __('Relevant tools'));
        $show->field('relevant_equipment', __('Relevant equipment'));
        $show->field('functional_board_of_directors', __('Functional board of directors'));
        $show->field('management_structure', __('Management structure'));
        $show->field('service_rules', __('Service rules'));
        $show->field('staff_appointment_letters', __('Staff appointment letters'));
        $show->field('payment_evidence', __('Payment evidence'));
        $show->field('filing_recording_system', __('Filing recording system'));
        $show->field('proper_storage', __('Proper storage'));
        $show->field('adequate_furniture', __('Adequate furniture'));
        $show->field('registered_accredited_trainers', __('Registered accredited trainers'));
        $show->field('tutors_teaching_qualification', __('Tutors teaching qualification'));
        $show->field('teaching_certificate_or_inservice_training', __('Teaching certificate or inservice training'));
        $show->field('proof_of_experience', __('Proof of experience'));
        $show->field('volunteers_training', __('Volunteers training'));
        $show->field('tutors_appraisal_mechanism', __('Tutors appraisal mechanism'));
        $show->field('training_request_response', __('Training request response'));
        $show->field('employment_demand_response', __('Employment demand response'));
        $show->field('curriculum_suitability', __('Curriculum suitability'));
        $show->field('curriculum_practice_theory_balance', __('Curriculum practice theory balance'));
        $show->field('practical_sessions', __('Practical sessions'));
        $show->field('timetable', __('Timetable'));
        $show->field('lesson_plans', __('Lesson plans'));
        $show->field('teaching_aids', __('Teaching aids'));
        $show->field('reference_materials', __('Reference materials'));
        $show->field('code_of_conduct', __('Code of conduct'));
        $show->field('guidance_counseling_officer', __('Guidance counseling officer'));
        $show->field('learner_grievance_procedures', __('Learner grievance procedures'));
        $show->field('confidentiality_guarantee', __('Confidentiality guarantee'));
        $show->field('facility_accessibility', __('Facility accessibility'));
        $show->field('tuition_fee_policy', __('Tuition fee policy'));
        $show->field('learning_environment', __('Learning environment'));
        $show->field('entry_requirements', __('Entry requirements'));
        $show->field('health_safety_policy', __('Health safety policy'));
        $show->field('health_safety_facilities', __('Health safety facilities'));
        $show->field('course_syllabus', __('Course syllabus'));
        $show->field('course_timetable', __('Course timetable'));
        $show->field('course_reference_materials', __('Course reference materials'));
        $show->field('entry_requirements_document', __('Entry requirements document'));
        $show->field('non_refundable_accreditation_fee', __('Non refundable accreditation fee'));
        $show->field('refundable_accreditation_fee_per_programme', __('Refundable accreditation fee per programme'));
        $show->field('accreditation_fee_refund_policy', __('Accreditation fee refund policy'));
        $show->field('status', __('Status'));
        $show->field('payment_status', __('Payment status'));
        $show->field('payment_date', __('Payment date'));
        $show->field('payment_reference', __('Payment reference'));
        $show->field('payment_details', __('Payment details'));
        $show->field('payment_amount', __('Payment amount'));
        $show->field('payment_method', __('Payment method'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new TrainingProviderAccreditation());

        $form->number('user_id', __('User id'));
        $form->textarea('application_date', __('Application date'));
        $form->textarea('establishment_name', __('Establishment name'));
        $form->textarea('street_address', __('Street address'));
        $form->textarea('postal_address', __('Postal address'));
        $form->textarea('website_address', __('Website address'));
        $form->textarea('contact_person', __('Contact person'));
        $form->textarea('telephone_number', __('Telephone number'));
        $form->textarea('mobile_number', __('Mobile number'));
        $form->textarea('email_address', __('Email address'));
        $form->textarea('applied_fields', __('Applied fields'));
        $form->textarea('applied_levels', __('Applied levels'));
        $form->textarea('number_of_registered_trainers', __('Number of registered trainers'));
        $form->textarea('number_of_accredited_trainers', __('Number of accredited trainers'));
        $form->textarea('official_examination_awarding_body', __('Official examination awarding body'));
        $form->textarea('number_of_trainees_male_pt', __('Number of trainees male pt'));
        $form->textarea('number_of_trainees_male_ft', __('Number of trainees male ft'));
        $form->textarea('number_of_trainees_female_pt', __('Number of trainees female pt'));
        $form->textarea('number_of_trainees_female_ft', __('Number of trainees female ft'));
        $form->textarea('trainer_name', __('Trainer name'));
        $form->textarea('naqaa_accreditation_number', __('Naqaa accreditation number'));
        $form->textarea('trainer_qualifications', __('Trainer qualifications'));
        $form->textarea('accredited_areas', __('Accredited areas'));
        $form->textarea('enrolment_dates', __('Enrolment dates'));
        $form->textarea('declaration_principal_ceo_contact_person_name', __('Declaration principal ceo contact person name'));
        $form->textarea('declaration_principal_ceo_contact_person_signature', __('Declaration principal ceo contact person signature'));
        $form->textarea('declaration_principal_ceo_contact_person_date', __('Declaration principal ceo contact person date'));
        $form->textarea('declaration_chairperson_name', __('Declaration chairperson name'));
        $form->textarea('declaration_chairperson_signature', __('Declaration chairperson signature'));
        $form->textarea('declaration_chairperson_date', __('Declaration chairperson date'));
        $form->textarea('submitted_by_name', __('Submitted by name'));
        $form->textarea('submitted_by_signature', __('Submitted by signature'));
        $form->textarea('submitted_by_date', __('Submitted by date'));
        $form->textarea('received_by_name', __('Received by name'));
        $form->textarea('received_by_signature', __('Received by signature'));
        $form->textarea('received_by_date', __('Received by date'));
        $form->textarea('adequate_facilities', __('Adequate facilities'));
        $form->textarea('clean_water_supply', __('Clean water supply'));
        $form->textarea('adequate_power_supply', __('Adequate power supply'));
        $form->textarea('library_resource_centre', __('Library resource centre'));
        $form->textarea('staffroom', __('Staffroom'));
        $form->textarea('centre_manager_office', __('Centre manager office'));
        $form->textarea('student_recreational_waiting_area', __('Student recreational waiting area'));
        $form->textarea('sitting_arrangement', __('Sitting arrangement'));
        $form->textarea('relevant_teaching_materials', __('Relevant teaching materials'));
        $form->textarea('relevant_tools', __('Relevant tools'));
        $form->textarea('relevant_equipment', __('Relevant equipment'));
        $form->textarea('functional_board_of_directors', __('Functional board of directors'));
        $form->textarea('management_structure', __('Management structure'));
        $form->textarea('service_rules', __('Service rules'));
        $form->textarea('staff_appointment_letters', __('Staff appointment letters'));
        $form->textarea('payment_evidence', __('Payment evidence'));
        $form->textarea('filing_recording_system', __('Filing recording system'));
        $form->textarea('proper_storage', __('Proper storage'));
        $form->textarea('adequate_furniture', __('Adequate furniture'));
        $form->textarea('registered_accredited_trainers', __('Registered accredited trainers'));
        $form->textarea('tutors_teaching_qualification', __('Tutors teaching qualification'));
        $form->textarea('teaching_certificate_or_inservice_training', __('Teaching certificate or inservice training'));
        $form->textarea('proof_of_experience', __('Proof of experience'));
        $form->textarea('volunteers_training', __('Volunteers training'));
        $form->textarea('tutors_appraisal_mechanism', __('Tutors appraisal mechanism'));
        $form->textarea('training_request_response', __('Training request response'));
        $form->textarea('employment_demand_response', __('Employment demand response'));
        $form->textarea('curriculum_suitability', __('Curriculum suitability'));
        $form->textarea('curriculum_practice_theory_balance', __('Curriculum practice theory balance'));
        $form->textarea('practical_sessions', __('Practical sessions'));
        $form->textarea('timetable', __('Timetable'));
        $form->textarea('lesson_plans', __('Lesson plans'));
        $form->textarea('teaching_aids', __('Teaching aids'));
        $form->textarea('reference_materials', __('Reference materials'));
        $form->textarea('code_of_conduct', __('Code of conduct'));
        $form->textarea('guidance_counseling_officer', __('Guidance counseling officer'));
        $form->textarea('learner_grievance_procedures', __('Learner grievance procedures'));
        $form->textarea('confidentiality_guarantee', __('Confidentiality guarantee'));
        $form->textarea('facility_accessibility', __('Facility accessibility'));
        $form->textarea('tuition_fee_policy', __('Tuition fee policy'));
        $form->textarea('learning_environment', __('Learning environment'));
        $form->textarea('entry_requirements', __('Entry requirements'));
        $form->textarea('health_safety_policy', __('Health safety policy'));
        $form->textarea('health_safety_facilities', __('Health safety facilities'));
        $form->textarea('course_syllabus', __('Course syllabus'));
        $form->textarea('course_timetable', __('Course timetable'));
        $form->textarea('course_reference_materials', __('Course reference materials'));
        $form->textarea('entry_requirements_document', __('Entry requirements document'));
        $form->textarea('non_refundable_accreditation_fee', __('Non refundable accreditation fee'));
        $form->textarea('refundable_accreditation_fee_per_programme', __('Refundable accreditation fee per programme'));
        $form->textarea('accreditation_fee_refund_policy', __('Accreditation fee refund policy'));
        $form->textarea('status', __('Status'))->default('Pending');
        $form->text('payment_status', __('Payment status'))->default('Not Paid');
        $form->textarea('payment_date', __('Payment date'));
        $form->textarea('payment_reference', __('Payment reference'));
        $form->textarea('payment_details', __('Payment details'));
        $form->textarea('payment_amount', __('Payment amount'));
        $form->textarea('payment_method', __('Payment method'));

        return $form;
    }
}
