<?php

namespace App\Http\Controllers;

use App\Applications;
use App\Attendedschool;
use App\Educationsummaries;
use App\ExamDetail;
use App\Mail\NewApplicationEmail;
use App\Payment;
use App\PostgraduateDoc;
use App\PrimaryInformations;
use App\Program;
use App\School;
use App\UndergraduateDoc;
use App\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Response;
use View;

session_start();

class ApplicantsController extends Controller
{
    public function __construct(Request $request, User $user, Educationsummaries $education_summary, Attendedschool $attended_school, ExamDetail $exam_detail, Program $program, PrimaryInformations $primary_informations, Applications $applications, PostgraduateDoc $postgraduate_doc, UndergraduateDoc $undergraduate_doc)
    {
        $this->middleware('auth');
        $this->middleware('verified');
        $this->request = $request;
        $this->user = $user;
        $this->education_summary = $education_summary;
        $this->attended_school = $attended_school;
        $this->exam_detail = $exam_detail;
        $this->program = $program;
        $this->primary_informations = $primary_informations;
        $this->applications = $applications;
        $this->postgraduate_doc = $postgraduate_doc;
        $this->undergraduate_doc = $undergraduate_doc;
        $this->id = null;
    }

    public function index()
    {
        $title = 'Dashboard';
        if (Auth::user()->role === 'admin' || Auth::user()->role === "superadmin") {
            return Redirect::to('admin/home');
        } elseif (Auth::user()->role === 'user') {
            $primary_informations = PrimaryInformations::select('*')->where('user_id', Auth::user()->id)->first();

            return view('applicants.master', compact('title', 'primary_informations'));
        }

    }

    public function link_to_programs()
    {
        $title = 'Schools and Programs';
        //============= Join programs and schools table =====================
        $programs = Program::join('schools', 'programs.school_id', '=', 'schools.id')->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')->paginate(15);
        $schools = School::paginate(15);



        $school_filters = School::all();
        $program_filters = Program::join('schools', 'programs.school_id', '=', 'schools.id')->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')->get();
        //=========== For Ajax paginating function===========================
        if (request()->ajax()) {
            return Response::json(view::make('applicants.schools_programs', array('posts' => $programs), compact('programs', 'schools', 'title'))->render());
        }
        return view('applicants.schools_programs', array('programs' => $programs), compact('title', 'programs', 'schools', 'school_filters', 'program_filters', 'school', 'country', 'program',
        'filter_country', 'filter_school', 'filter_program', 'filter_school_type', 'filter_state', 'filter_program_level',
        'filter_tuition_fee', 'filter_application_fee', 'filter_post_sub_category'
    ));
    }

//   Filter function

    public function apply_filter()
    {
        $title = 'Schools and Programs';
        $request = $this->request;
        $filter_program = $request->program;
        $filter_school = $request->school;
        $filter_country = $request->country;
        $filter_state = $request->state;
        $filter_school_type = $request->school_type;

        // program filters request
        $filter_program_level = $request->program_level;
        $filter_post_sub_category = $request->post_sub_category;
        $filter_tuition_fee = $request->tuition_fee*1000;
        $filter_application_fee = $request->application_fee;
        if($this->request->application_fee == 0){
            $this->request->application_fee = '';
        }

        if($this->request->tuition_fee == 0){
            $this->request->tuition_fee = '';
        }

        if (!isset($filter_school) && !isset($filter_program) && !isset($filter_country) && !isset($filter_state)
        && !isset($filter_school_type)  && !isset($filter_program_level) && !isset($filter_post_sub_category)
            && !isset($filter_tuition_fee) && !isset($filter_application_fee)) {
            $search_programs = Program::join('schools', 'programs.school_id', '=', 'schools.id')->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')->paginate(15);
            $search_schools = School::paginate(15);
        } else {

                $search_programs = Program::join('schools', 'programs.school_id', '=', 'schools.id')
                ->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')
                ->when(isset($this->request->program), function ($query) {
                    return $query->where('program_name', 'LIKE', "%{$this->request->program}%");
                })->when(isset($this->request->country), function ($query) {
                return $query->where('schools.location', 'LIKE', "%{$this->request->country}%");
            })->when(isset($this->request->school), function ($query) {
                return $query->where('schools.school_name', $this->request->school);
            })->when(isset($this->request->state), function ($query) {
                return $query->where('schools.location', 'LIKE', "%{$this->request->state}%");
            })->when(isset($this->request->school_type), function ($query) {
                return $query->where('schools.school_name', 'LIKE', "%{$this->request->school_type}%");
            })->when(!empty($this->request->tuition_fee), function ($query) {
                return $query->whereBetween('programs.tution_fee', [$this->request->tuition_fee*1000-500, $this->request->tuition_fee*1000+500]);
            })->latest('programs.created_at')->paginate(15);

            $search_schools_id = School::join('programs', 'programs.school_id', '=', 'schools.id')
                ->when(isset($this->request->country), function ($query) {
                    $query->where('location', 'LIKE', "%{$this->request->country}%");
                })->when(isset($this->request->school), function ($query) {
                $query->where('school_name', $this->request->school);
            })->when(!empty($this->request->tuition_fee), function ($query) {
                return $query->whereBetween('programs.tution_fee', [$this->request->tuition_fee*1000-500, $this->request->tuition_fee*1000+500]);
            })->select('schools.id')->get();

            $search_schools = School::whereIn('id', $search_schools_id)->latest()->paginate(15);

        }


        $school = '';
        $program = '';
        $country = '';
        $school_filters = School::all();
        $program_filters = Program::join('schools', 'programs.school_id', '=', 'schools.id')->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')->get();
        //================== For pagination via ajax ===============================
        if (request()->ajax()) {
            return view('applicants.schools_programs', array('posts' => $search_programs), compact('title', 'search_programs', 'search_schools', 'school'));
        }
        return view('applicants.schools_programs', array('posts' => $search_programs), compact('title', 'search_programs', 'search_schools', 'school','school_filters', 'country', 'program', 'program_filters', 'filter_country',
        'filter_school', 'filter_program', 'filter_school_type', 'filter_state',
         'filter_program_level', 'filter_tuition_fee', 'filter_application_fee', 'filter_post_sub_category'));

    }

    public function search()
    {
        $title = 'Searched Items';
        $request = $this->request;
        $program = $request->program;
        $school = $request->school;
        $country = $request->country;
        $filter_country = '';
        $filter_program = '';
        $filter_program_level = '';
        $filter_school = '';
        $filter_school_type = '';
        $filter_state = '';
        $filter_application_fee = '';
        $filter_tuition_fee = '';
        $filter_post_sub_category = '';

        if (!isset($school) && !isset($program) && !isset($country)) {
            $search_programs = Program::join('schools', 'programs.school_id', '=', 'schools.id')->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')->paginate(15);
            $search_schools = School::paginate(15);
        }

        // check if it's only a school that's entered
        if (isset($school) && !isset($program) && !isset($country)) {
            // search for only school
            $search_schools = School::where(function ($query) {
                $query->where('school_name', $this->request->school);

            })->latest('schools.created_at')->paginate(15);

            $search_programs = Program::join('schools', 'programs.school_id', '=', 'schools.id')
                ->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')
                ->where(function ($query) {
                    $query->where('program_name', 'LIKE', "%{$this->request->program}%");
                })->where(function ($query) {
                $query->where('schools.school_name', $this->request->school);
            })->latest('programs.created_at')->paginate(15);

        } elseif (isset($program) && !isset($school) && !isset($country)) {
            // search for only program
            $search_programs = Program::join('schools', 'programs.school_id', '=', 'schools.id')
                ->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')
                ->where(function ($query) {
                    $query->where('program_name', 'LIKE', "%{$this->request->program}%");

                })->latest('programs.created_at')->paginate(15);

            $search_schools_id = School::join('programs', 'programs.school_id', '=', 'schools.id')
            ->where(function ($query) {
                $query->where('programs.program_name', 'LIKE', "%{$this->request->program}%");

                })->select('schools.id')->get();

                $search_schools = School::whereIn('id', $search_schools_id)->latest()->paginate(15);

        } elseif (isset($country) && !isset($program) && !isset($school)) {
            // search school based on  country
            $search_schools = School::where(function ($query) {
                $query->where('location', 'LIKE', "%{$this->request->country}%");
            })->latest('schools.created_at')->paginate(15);

            $search_programs = Program::join('schools', 'programs.school_id', '=', 'schools.id')
                ->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')
                ->where(function ($query) {
                    $query->where('program_name', 'LIKE', "%{$this->request->program}%");
                })->where(function ($query) {
                $query->where('schools.location', 'LIKE', "%{$this->request->country}%");
            })->latest('programs.created_at')->paginate(15);
        } elseif (isset($program) && isset($country) && !isset($school)) {
            // search based on country and program

            $search_programs = Program::join('schools', 'programs.school_id', '=', 'schools.id')
                ->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')
                ->where(function ($query) {
                    $query->where('program_name', 'LIKE', "%{$this->request->program}%");
                })->where(function ($query) {
                $query->where('schools.location', 'LIKE', "%{$this->request->country}%");
            })->latest('programs.created_at')->paginate(15);

            $search_schools_id = School::join('programs', 'programs.school_id', '=', 'schools.id')
            ->where(function ($query) {
                $query->where('schools.location', 'LIKE', "%{$this->request->country}%");
            })->where(function ($query) {
                    $query->where('programs.program_name', 'LIKE', "%{$this->request->program}%");
                })->select('schools.id')->get();
            $search_schools = School::whereIn('id', $search_schools_id)->latest()->paginate(15);

        } elseif(!isset($program) && isset($school) && isset($country)){

            // search for school and country
            $search_programs = Program::join('schools', 'programs.school_id', '=', 'schools.id')
                ->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')
                ->where(function ($query) {
                    $query->where('schools.location', 'LIKE', "%{$this->request->country}%");
                })->where(function ($query) {
                $query->where('schools.school_name', $this->request->school);
            })->latest('programs.created_at')->paginate(15);

           $search_schools = School::where(function ($query) {
                $query->where('location', 'LIKE', "%{$this->request->country}%");
            })->where(function ($query) {
                $query->where('school_name', $this->request->school);
            })->latest('schools.created_at')->paginate(15);


        } elseif (isset($program) && isset($school) && !isset($country)) {
            //search for program and country

            $search_programs = Program::join('schools', 'programs.school_id', '=', 'schools.id')
                ->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')
                ->where(function ($query) {
                    $query->where('program_name', 'LIKE', "%{$this->request->program}%");
                })->where(function ($query) {
                $query->where('schools.school_name', $this->request->school);
            })->latest('programs.created_at')->paginate(15);

            $search_schools = School::where(function ($query) {
                $query->where('school_name', $this->request->school);
            })->latest('schools.created_at')->paginate(15);



        } elseif (isset($country) && isset($program) && isset($school)) {
            // search for country , program, and school
            $search_programs = Program::join('schools', 'programs.school_id', '=', 'schools.id')
                ->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')
                ->where(function ($query) {
                    $query->where('program_name', 'LIKE', "%{$this->request->program}%");
                })->where(function ($query) {
                $query->where('schools.location', 'LIKE', "%{$this->request->country}%");
            })->where(function ($query) {
                $query->where('schools.school_name', $this->request->school);

            })->latest('programs.created_at')->paginate(15);

            $search_schools = School::where(function ($query) {
                $query->where('location', 'LIKE', "%{$this->request->country}%");
            })->where(function ($query) {
                $query->where('school_name', $this->request->school);
            })->latest('schools.created_at')->paginate(15);

        }

        // return response()->json([
        //     'message' => 'successful',
        //     'programs' => $search_programs,
        //     'schools' => $search_schools,
        // ]);

        $school_filters = School::all();
        $program_filters = Program::join('schools', 'programs.school_id', '=', 'schools.id')->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')->get();
        //================== For pagination via ajax ===============================
        if (request()->ajax()) {
            return view('applicants.schools_programs', array('posts' => $search_programs), compact('title', 'search_programs', 'search_schools', 'school'));
        }
        return view('applicants.schools_programs', array('posts' => $search_programs), compact('title', 'search_programs', 'search_schools', 'school', 'school_filters', 'program_filters', 'country', 'school', 'program',
        'filter_country',
        'filter_school', 'filter_program', 'filter_school_type', 'filter_state',
         'filter_program_level', 'filter_tuition_fee', 'filter_application_fee', 'filter_post_sub_category'
    ));
    }

    public function link_to_school_section($id)
    {
        $title = 'School';
        //============ Join the programs and schools table ====================
        $program = Program::join('schools', 'programs.school_id', '=', 'schools.id')->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'schools.application_fee as application_fee_school')->where('programs.id', $id)->first();

        return view('applicants.school_section', compact('title', 'program'));
    }

    public function link_to_school_programs($id)
    {
        $title = 'School and programs';

        //============ Join the programs and schools table ====================
        $school = School::where('schools.id', $id)->first();
        $programs = Program::where('school_id', $id)->paginate(10);

        //================== For pagination via ajax ===============================
        if (request()->ajax()) {
            return Response::json(view::make('applicants.school', array('posts' => $programs), compact('title', 'school', 'programs'))->render());
        }

        return view('applicants.school', compact('title', 'school', 'programs'));
    }

    public function link_to_payments()
    {
        $title = 'Payments';
        $payments = Payment::where('user_id', Auth::user()->id)->get();

        return view('applicants.payments', compact('title', 'payments'));
    }

    public function link_to_eligibility()
    {
        $title = 'Eligibility';
        if ($this->education_summary->find(Auth::user()->id) !== null) {
            $education_summary = $this->education_summary->find(Auth::user()->id)->first();
        }

        if ($this->education_summary->find(Auth::user()->id) === null) {
            $education_summary = $this->education_summary;
        }

        if (Attendedschool::where('user_id', Auth::user()->id)->where('type', 'post_graduate')->first() !== null) {
            $attended_school_post = Attendedschool::where('user_id', Auth::user()->id)->where('type', 'post_graduate')->first();
        }

        if (Attendedschool::where('user_id', Auth::user()->id)->where('type', 'bachelor')->first() !== null) {
            $attended_school_bachelor = Attendedschool::where('user_id', Auth::user()->id)->where('type', 'bachelor')->first();
        }

        if (Attendedschool::where('user_id', Auth::user()->id)->where('type', 'grade')->first() !== null) {
            $attended_school_grade = Attendedschool::where('user_id', Auth::user()->id)->where('type', 'grade')->first();
        }

        if (ExamDetail::where('user_id', Auth::user()->id)->where('exam_type', 'IELTS')->first() !== null) {
            $exam_detail = ExamDetail::where('user_id', Auth::user()->id)->where('exam_type', 'IELTS')->first();
        }

        if (ExamDetail::where('user_id', Auth::user()->id)->where('exam_type', 'TOEFL')->first() !== null) {
            $exam_detail_1 = ExamDetail::where('user_id', Auth::user()->id)->where('exam_type', 'TOEFL')->first();
        }

        if (ExamDetail::where('user_id', Auth::user()->id)->where('exam_type', 'Duolingo_English_Test')->first() !== null) {
            $exam_detail_2 = ExamDetail::where('user_id', Auth::user()->id)->where('exam_type', 'Duolingo_English_Test')->first();
        }

        if (ExamDetail::where('user_id', Auth::user()->id)->where('exam_type', 'gre')->first() !== null) {
            $gre = ExamDetail::where('user_id', Auth::user()->id)->where('exam_type', 'gre')->first();
        }

        if (ExamDetail::where('user_id', Auth::user()->id)->where('exam_type', 'gmat')->first() !== null) {
            $gmat = ExamDetail::where('user_id', Auth::user()->id)->where('exam_type', 'gmat')->first();
        }

        return view('applicants.eligibility', compact('title', 'education_summary', 'attended_school_post', 'attended_school_bachelor', 'attended_school_grade', 'exam_detail', 'exam_detail_1', 'exam_detail_2', 'gmat', 'gre'));

    }

    public function link_to_applications()
    {
        $title = "Applications";
        $applications = Applications::leftjoin('programs', 'applications.program_id', '=', 'programs.id')->leftjoin('schools', 'programs.school_id', '=', 'schools.id')->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'applications.id as application_id')->where('applications.user_id', Auth::user()->id)->get();

        $uploaded_documents_pos = PostgraduateDoc::where('user_id', Auth::user()->id)->first();
        $uploaded_documents_un = UndergraduateDoc::where('user_id', Auth::user()->id)->first();
        $last_application = Applications::leftjoin('programs', 'applications.program_id', '=', 'programs.id')->leftjoin('schools', 'programs.school_id', '=', 'schools.id')->select('*', 'programs.id as program_id', 'schools.id as school_id', 'programs.application_fee as application_fee', 'applications.id as application_id')->where('applications.user_id', Auth::user()->id)->orderBy('applications.created_at', 'DESC')->first();

        return view('applicants.registered_applications', compact('title', 'applications', 'uploaded_documents_un', 'last_application', 'uploaded_documents_pos'));
    }

//============== Add to applications ================================
    public function add_to_applications()
    {

        $request = $this->request;
        $application = $this->applications;
        // check if such program already exist for the user
        $existing_program = $application->select('program_id')->where('program_id', $request->program_id)->where('user_id', Auth::user()->id)->first();
        if ($existing_program !== null) {
            return redirect()->back()->with('error', 'Already applied to the program');
        }

        $application->program_id = $request->program_id;
        $application->user_id = Auth::user()->id;
        $application->payment = "pending";
        $application->admission_status = "PROCESSING...";
        $application->save();
        $email = 'consulting@skynedconsults.com';
        Mail::to($email)->send(new NewApplicationEmail());

        return Redirect::to('applicants/applications');
    }

//============ profile page =================================

    public function profile()
    {
        $data['title'] = "Profile";
        return view('applicants.profile', $data);
    }

///////////////////////////////////////////////////////////
    public function submit_personal_info()
    {
        $request = $this->request;
        $user_id = Auth::user()->id;
        $user = $this->user->find($user_id);
        $user->first_name = $request->first_name;
        $user->middle_name = $request->middle_name;
        $user->last_name = $request->last_name;
        $user->d_o_b = $request->d_o_b;
        $user->first_language = $request->first_language;
        $user->country_of_citizenship = $request->country;
        $user->passport_number = $request->passport_number;
        if (isset(Auth::user()->gender)) {
            $user->gender = Auth::user()->gender;
        } else {
            $user->gender = $request->gender;
        }
        if (isset(Auth::user()->marital_status)) {
            $user->marital_status = Auth::user()->marital_status;
        } else {
            $user->marital_status = $request->marital_status;
        }

        $user->save();

        return Redirect::to('/eligibility?success=Personal Information updated successfully.');
    }

    public function submit_address_details()
    {
        $request = $this->request;
        $user_id = Auth::user()->id;
        $user = $this->user->find($user_id);
        $user->address = $request->address;
        $user->city = $request->city;
        $user->country_of_citizenship = $request->country;
        $user->state = $request->province;
        $user->postal_code = $request->postal_code;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->emergency_phone = $request->emergency_phone;
        $user->emergency_name = $request->emergency_name;
        $user->emergency_relationship = $request->emergency_relationship;
        $user->emergency_email = $request->emergency_email;
        $user->save();

        return Redirect::to('/eligibility?success=Address details updated successfully.');

    }

    public function submit_education_summary()
    {
        $request = $this->request;
        $user_id = Auth::user()->id;
        if ($this->education_summary->find($user_id) !== null) {
            $education_summary = $this->education_summary->find($user_id);
        } else {
            $education_summary = $this->education_summary;
            $education_summary->user_id = $user_id;
        }
        $education_summary->country = $request->country;
        $education_summary->highest_level_of_education = $request->level_of_education;
        $education_summary->grading_scheme = $request->grading_scheme;
        $education_summary->grade_scale = $request->grade_scale;
        $education_summary->grade_average = $request->grading_average;
        $education_summary->save();

        return Redirect::to('/eligibility?page=2&success=Education summary updated successfully.');

    }

    public function submit_postgraduate_school()
    {
        $request = $this->request;
        $user_id = Auth::user()->id;
        $id = $request->id;
        if (Attendedschool::find($id) !== null) {
            $attended_school = Attendedschool::find($id);
        } else {
            $attended_school = $this->attended_school;

        }
        $attended_school->user_id = $user_id;
        $attended_school->type = $request->type;
        $attended_school->level_of_education = $request->level_of_education;
        $attended_school->country = $request->country;
        $attended_school->institution = $request->institution;
        $attended_school->language = $request->language;
        $attended_school->from = $request->institution_from;
        $attended_school->to = $request->institution_to;
        $attended_school->degree_awarded = $request->degree_awarded;
        $attended_school->degree_awarded_on = $request->degree_awarded_on;
        $attended_school->school_address = $request->school_address;
        $attended_school->city = $request->city;
        $attended_school->province = $request->province;
        $attended_school->postal_code = $request->postal_code;

        $attended_school->save();

        return Redirect::to('/eligibility?page=2&success=Schools attended details updated!.');
    }

    public function submit_test_scores()
    {
        $request = $this->request;
        $user_id = Auth::user()->id;
        $id = $request->id;
        //Check if such record exist or not
        if (($this->exam_detail->find($id)) !== null) {
            $exam_detail = $this->exam_detail->find($id);
            $exam_type = $exam_detail->exam_type;
        } else {
            $exam_detail = $this->exam_detail;
            $exam_type = $request->exam_type;

        }
        $exam_detail->user_id = $user_id;
        $exam_detail->exam_type = $request->exam_type;
        // For Duolingo English Test
        if (isset($request->date_of_exam_2) && $exam_type === 'Duolingo_English_Test') {
            $exam_detail->date_of_exam = $request->date_of_exam_2;
            if (isset($request->overall_scores)) {
                $exam_detail->overall_scores = $request->overall_scores;
            }
        } elseif (isset($request->reading_score_1) && $exam_type === 'TOEFL') {
            // For TOEFL
            $exam_detail->date_of_exam = $request->date_of_exam_1;
            $exam_detail->reading_score = $request->reading_score_1;
            $exam_detail->listening_score = $request->listening_scores_1;
            $exam_detail->speaking_score = $request->speaking_scores_1;
            $exam_detail->writing_score = $request->writing_scores_1;

        } elseif (isset($request->reading_score) && $exam_type === 'IELTS') {
            // For IELTS
            $exam_detail->date_of_exam = $request->date_of_exam;
            $exam_detail->reading_score = $request->reading_score;
            $exam_detail->listening_score = $request->listening_scores;
            $exam_detail->speaking_score = $request->speaking_scores;
            $exam_detail->writing_score = $request->writing_scores;

        }

        $exam_detail->save();

        return Redirect::to('/eligibility?page=3&success=Test scores updated successfully!.');
    }

/////////////////////// background info //////////////

    public function submit_background_informations()
    {
        $request = $this->request;
        $user_id = Auth::user()->id;
        $user = $this->user->find($user_id);
        $background_informations->permit_or_visa = $request->visa_permit;
        $background_informations->refused_visa = $request->refused_visa;

        $background_informations->save();

        return Redirect::to('/eligibility?page=4&success=Background Info updated successfully!.');
    }

///////////////////////

    public function additional_qualification()
    {
        $request = $this->request;
        $user_id = Auth::user()->id;
        if (isset($request->id) && $exam_type = 'gre') {
            $id = $request->id;
            return $id;
        } else {
            $id = '';
        }

        // check if the records exist
        if (($this->exam_detail->find($id)) !== null) {
            $exam_detail = $this->exam_detail->find($id);
            $exam_type = $exam_detail->exam_type;
        } else {
            $exam_detail = $this->exam_detail;
            $exam_type = $request->exam_type;

        }
        $exam_detail->user_id = $user_id;
        if ($exam_type = 'gre' && isset($request->verbal_score)) {
            $exam_detail->date_of_exam = $request->exam_date;
            $exam_detail->exam_type = $request->type;
            $exam_detail->verbal_score = $request->verbal_score;
            $exam_detail->verbal_rank = $request->verbal_rank;
            $exam_detail->quantitiative_score = $request->q_score;
            $exam_detail->quantitiative_rank = $request->q_rank;
            $exam_detail->writing_score = $request->w_score;
            $exam_detail->writing_rank = $request->w_rank;
        }

        $exam_detail->save();

        return Redirect::to('/eligibility?page=3&success=GRE scores updated successfully!.');
    }

    public function additional_qualification_2()
    {
        $request = $this->request;
        $id = $request->id;
        $user_id = Auth::user()->id;
        if (isset($request->id_1) && $exam_type = 'gmat') {
            $id = $request->id_1;
        }

        // check if the records exist
        if (($this->exam_detail->find($id)) !== null) {
            $exam_detail = $this->exam_detail->find($id);
            $exam_type = $exam_detail->exam_type;
        } else {
            $exam_detail = $this->exam_detail;
            $exam_type = $request->exam_type;

        }
        $exam_detail->user_id = $user_id;
        if ($exam_type = 'gmat' && isset($request->verbal_score_2)) {
            $exam_detail->date_of_exam = $request->exam_date_2;
            $exam_detail->exam_type = $request->type;
            $exam_detail->verbal_score = $request->verbal_score_2;
            $exam_detail->verbal_rank = $request->verbal_rank_2;
            $exam_detail->quantitiative_score = $request->q_score_2;
            $exam_detail->quantitiative_rank = $request->q_rank_2;
            $exam_detail->writing_score = $request->w_score_2;
            $exam_detail->writing_rank = $request->w_rank_2;
        }

        $exam_detail->save();

        return Redirect::to('/eligibility?page=3&success=GMAT scores updated successfully!.');
    }

    public function create_primary_informations()
    {
        $request = $this->request;
        $id = $request->id;
        if (isset($id)) {
            $primary_informations = $this->primary_informations->select('*')->where('user_id', Auth::user()->id);
            $primary_informations = $this->primary_informations;
            $primary_informations->prev_edu_history = $request->prev_edu_history;
            $primary_informations->course_studied = $request->course_studied;
            $primary_informations->d_o_b = $request->d_o_b;
            $primary_informations->enrolled_question = $request->enrolled_question;
            $primary_informations->budget_fees = $request->budget_fees;
            $primary_informations->sponsor = $request->sponsor;
            $primary_informations->destination = $request->destination;
            $primary_informations->program = $request->program;
            $primary_informations->visa = $request->visa;
            $primary_informations->study_gap = $request->study_gap;
            $primary_informations->graduated_on = $request->graduated_on;
            $primary_informations->save();

            return Redirect::to('/eligibility?success=Primary Informations updated successfully. Kindly complete your eligibility process');
        } else {
            $primary_informations = $this->primary_informations;
            $primary_informations->user_id = Auth::user()->id;
            $primary_informations->prev_edu_history = $request->prev_edu_history;
            $primary_informations->course_studied = $request->course_studied;
            $primary_informations->d_o_b = $request->d_o_b;
            $primary_informations->enrolled_question = $request->enrolled_question;
            $primary_informations->budget_fees = $request->budget_fees;
            $primary_informations->sponsor = $request->sponsor;
            $primary_informations->destination = $request->destination;
            $primary_informations->program = $request->program;
            $primary_informations->visa = $request->visa;
            $primary_informations->study_gap = $request->study_gap;
            $primary_informations->graduated_on = $request->graduated_on;
            $primary_informations->save();

            return Redirect::to('/eligibility?success=Primary Informations created successfully. Kindly complete your eligibility process.');
        }
    }

    public function submit_postgraduate_docs()
    {
        $data = array();
        $request = $this->request;
        $postgraduate_doc = $this->postgraduate_doc;
        Validator::make($data, [
            'resume.*' => 'jpg,jpeg,png',
            'bsc_certificate.*' => 'jpg,jpeg,png',
            'bsc_transcripts.*' => 'jpg,jpeg,png',
            'references.*' => 'jpg,jpeg,png',
            'transcripts.*' => 'jpg,jpeg,png',
            'waec_result.*' => 'jpg,jpeg,png',
            'passport.*' => 'jpg,jpeg,png',
        ]);

        $postgraduate_doc->user_id = $request->user_id;

        if ($request->hasFile('passport')) {
            $passport = $request->file('passport');
            $name = Str::uuid() . "." . $passport->getClientOriginalExtension();
            $passport->move(public_path() . '/storage/docs', $name);
            $postgraduate_doc->passport = $name;
        }

        if ($request->hasFile('resume')) {
            $resume = $request->file('resume');
            $name = Str::uuid() . "." . $resume->getClientOriginalExtension();
            $resume->move(public_path() . '/storage/docs', $name);
            $postgraduate_doc->resume = $name;
        }

        if ($request->hasFile('waec_result')) {
            $waec_result = $request->file('waec_result');
            $name = Str::uuid() . "." . $waec_result->getClientOriginalExtension();
            $waec_result->move(public_path() . '/storage/docs', $name);
            $postgraduate_doc->waec = $name;
        }

        if ($request->hasFile('bsc_certificate')) {
            $bsc_certificate = $request->file('bsc_certificate');
            $name = Str::uuid() . "." . $bsc_certificate->getClientOriginalExtension();
            $bsc_certificate->move(public_path() . '/storage/docs', $name);
            $postgraduate_doc->bsc_certificate = $name;

        }

        $data_1s = array();
        $data_2s = array();
        $data_3s = array();
        if ($request->hasFile('bsc_transcripts')) {
            $bsc_transcripts = $request->file('bsc_transcripts');
            foreach ($bsc_transcripts as $bsc_transcript) {
                $name = Str::uuid() . "." . $bsc_transcript->getClientOriginalExtension();
                $bsc_transcript->move(public_path() . '/storage/docs', $name);
                array_push($data_1s, $name);
            }
        }

        if ($request->hasFile('references')) {
            $references = $request->file('references');
            foreach ($references as $reference) {
                $name_1 = Str::uuid() . "." . $reference->getClientOriginalExtension();
                $reference->move(public_path() . '/storage/docs', $name_1);
                array_push($data_2s, $name_1);
            }
        }

        if ($request->hasFile('transcripts')) {
            $transcripts = $request->file('transcripts');
            foreach ($transcripts as $transcript) {
                $name_2 = Str::uuid() . "." . $transcript->getClientOriginalExtension();
                $transcript->move(public_path() . '/storage/docs', $name_2);
                array_push($data_3s, $name_2);
            }
        }
        $postgraduate_doc->transcripts = json_encode($data_3s);
        $postgraduate_doc->references = json_encode($data_2s);
        $postgraduate_doc->bsc_transcripts = json_encode($data_1s);
        $postgraduate_doc->emergency_contact = $request->emergency_contact;

        $postgraduate_doc->save();

    }

    public function submit_undergraduate_docs()
    {
        $data = array();
        $request = $this->request;
        $undergraduate_doc = $this->undergraduate_doc;
        Validator::make($data, [
            'resume.*' => 'jpg,jpeg,png',
            'bsc_certificate.*' => 'jpg,jpeg,png',
            'bsc_transcripts.*' => 'jpg,jpeg,png',
            'references.*' => 'jpg,jpeg,png',
            'transcripts.*' => 'jpg,jpeg,png',
            'waec_result.*' => 'jpg,jpeg,png',
        ]);

        $undergraduate_doc->user_id = $request->user_id;

        if ($request->hasFile('passport')) {
            $passport = $request->file('passport');
            $name = Str::uuid() . "." . $passport->getClientOriginalExtension();
            $passport->move(public_path() . '/storage/docs', $name);
            $undergraduate_doc->passport = $name;
        }

        if ($request->hasFile('resume')) {
            $resume = $request->file('resume');
            $name = Str::uuid() . "." . $resume->getClientOriginalExtension();
            $resume->move(public_path() . '/storage/docs', $name);
            $undergraduate_doc->resume = $name;
        }

        if ($request->hasFile('waec_result')) {
            $waec_result = $request->file('waec_result');
            $name = Str::uuid() . "." . $waec_result->getClientOriginalExtension();
            $waec_result->move(public_path() . '/storage/docs', $name);
            $undergraduate_doc->waec = $name;
        }

        if ($request->hasFile('waec_card')) {
            $waec_card = $request->file('waec_card');
            $name = Str::uuid() . "." . $waec_card->getClientOriginalExtension();
            $waec_card->move(public_path() . '/storage/docs', $name);
            $undergraduate_doc->waec_scratch_card = $name;

        }

        $data_3s = array();

        if ($request->hasFile('transcripts')) {
            $transcripts = $request->file('transcripts');
            foreach ($transcripts as $transcript) {
                $name_2 = Str::uuid() . "." . $transcript->getClientOriginalExtension();
                $transcript->move(public_path() . '/storage/docs', $name_2);
                array_push($data_3s, $name_2);
            }
        }
        $undergraduate_doc->transcripts = json_encode($data_3s);
        $undergraduate_doc->save();

    }

//================= Ajax fetching function ===========================

    public function fetch_program_details(Request $request)
    {
        if ($request->id) {
            $id = $request->id;

            $row = Program::select('programs.id AS program_id', 'programs.program_name AS program_name', 'programs.tution_fee AS tution_fee', 'programs.application_fee As application_fee', 'programs.requirements AS requirements', 'programs.school_id AS school_id')->where('id', $id)->first();
            $response = json_encode($row);
            return $response;
        }
    }

    public function applications_details(Request $request)
    {
        if ($request->id) {
            $id = $request->id;

            $row = Applications::leftjoin('programs', 'applications.program_id', '=', 'programs.id')->select('programs.application_fee as application_fee', 'applications.id as application_id')->where('applications.id', $id)->first();
            $response = json_encode($row);
            return $response;
        }
    }

    public function check_if_docs_exists(Request $request)
    {
        if ($request->user_id) {
            $user_id = $request->user_id;

            $row = PostgraduateDoc::where('user_id', Auth::user()->id)->first();
            $row_1 = UndergraduateDoc::where('user_id', Auth::user()->id)->first();

            if (!isset($row->user_id) || !isset($row_1->user_id)) {
                if (isset($row->user_id)) {
                    return 'success';
                } elseif (isset($row_1->user_id)) {
                    return 'success';
                } else {
                    return 'failed';
                }

            } else {

                return 'success';

            }

        }
    }
}
