<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DummyController extends Controller
{
    /**
     * Dashboard view with statistics and recent data
     */
    public function dashboard()
    {
        $data = [
            'childrenCount' => 42,
            'normalNutritionCount' => 35,
            'vaccinationsDueCount' => 8,
            'malnutritionCount' => 7,
            'recentChildren' => [
                ['id' => 1, 'name' => 'Emma Johnson', 'age' => '2 years 3 months', 'lastVisit' => '2023-06-15', 'zScore' => 0.5, 'status' => 'normal'],
                ['id' => 2, 'name' => 'Oliver Smith', 'age' => '1 year 5 months', 'lastVisit' => '2023-06-10', 'zScore' => -1.8, 'status' => 'moderate'],
                ['id' => 3, 'name' => 'Sophia Williams', 'age' => '3 years 1 month', 'lastVisit' => '2023-06-05', 'zScore' => 1.2, 'status' => 'normal'],
                ['id' => 4, 'name' => 'Lucas Brown', 'age' => '4 years 6 months', 'lastVisit' => '2023-05-28', 'zScore' => -2.5, 'status' => 'severe'],
                ['id' => 5, 'name' => 'Ava Davis', 'age' => '6 months', 'lastVisit' => '2023-06-12', 'zScore' => 0.8, 'status' => 'normal'],
            ],
            'upcomingImmunizations' => [
                ['childName' => 'Emma Johnson', 'vaccine' => 'MMR', 'dueDate' => '2023-07-20', 'status' => 'upcoming'],
                ['childName' => 'Oliver Smith', 'vaccine' => 'DTP', 'dueDate' => '2023-07-15', 'status' => 'due'],
                ['childName' => 'Sophia Williams', 'vaccine' => 'Hepatitis B', 'dueDate' => '2023-07-10', 'status' => 'overdue'],
                ['childName' => 'Lucas Brown', 'vaccine' => 'Polio', 'dueDate' => '2023-07-25', 'status' => 'upcoming'],
                ['childName' => 'Ava Davis', 'vaccine' => 'BCG', 'dueDate' => '2023-07-05', 'status' => 'due'],
            ],
        ];

        return view('claude.dashboard', $data);
    }

    /**
     * Show child profile
     */
    public function showChild($id)
    {
        $child = [
            'id' => $id,
            'name' => 'Emma Johnson',
            'age' => '2 years 3 months',
            'gender' => 'Female',
            'dob' => '2021-03-15',
            'bloodType' => 'O+',
            'allergies' => 'None',
            'parentName' => 'Sarah Johnson',
            'relationship' => 'Mother',
            'contact' => '+1 (555) 123-4567',
            'address' => '123 Main St, City, State',
            'weight' => '12.5',
            'height' => '87',
            'bmi' => '16.5',
            'weightStatus' => 'success',
            'weightStatusText' => 'Normal',
            'heightStatus' => 'success',
            'heightStatusText' => 'Normal',
            'bmiStatus' => 'success',
            'bmiStatusText' => 'Normal',
            'weightForAgeStatus' => 'normal',
            'weightForAgeZScore' => '0.5',
            'heightForAgeStatus' => 'normal',
            'heightForAgeZScore' => '0.3',
            'weightForHeightStatus' => 'normal',
            'weightForHeightZScore' => '0.7',
            'bmiForAgeStatus' => 'normal',
            'bmiForAgeZScore' => '0.4',
            'motorSkills' => 85,
            'language' => 75,
            'social' => 90,
            'cognitive' => 80,
        ];

        $visits = [
            ['date' => '2023-06-15', 'type' => 'Routine Checkup', 'notes' => 'Healthy development, all vitals normal'],
            ['date' => '2023-05-10', 'type' => 'Vaccination', 'notes' => 'MMR vaccine administered'],
            ['date' => '2023-04-05', 'type' => 'Sick Visit', 'notes' => 'Treated for common cold, recovered well'],
            ['date' => '2023-03-15', 'type' => 'Routine Checkup', 'notes' => 'Growth parameters within normal range'],
            ['date' => '2023-02-20', 'type' => 'Vaccination', 'notes' => 'DTP booster administered'],
        ];

        $immunizations = [
            ['vaccine' => 'BCG', 'dose' => '1', 'dateGiven' => '2021-03-20', 'nextDue' => '-', 'status' => 'complete'],
            ['vaccine' => 'Hepatitis B', 'dose' => '1', 'dateGiven' => '2021-03-20', 'nextDue' => '2021-04-20', 'status' => 'complete'],
            ['vaccine' => 'Hepatitis B', 'dose' => '2', 'dateGiven' => '2021-04-20', 'nextDue' => '2021-10-20', 'status' => 'complete'],
            ['vaccine' => 'Hepatitis B', 'dose' => '3', 'dateGiven' => '2021-10-20', 'nextDue' => '-', 'status' => 'complete'],
            ['vaccine' => 'DTP', 'dose' => '1', 'dateGiven' => '2021-05-20', 'nextDue' => '2021-07-20', 'status' => 'complete'],
            ['vaccine' => 'DTP', 'dose' => '2', 'dateGiven' => '2021-07-20', 'nextDue' => '2021-09-20', 'status' => 'complete'],
            ['vaccine' => 'DTP', 'dose' => '3', 'dateGiven' => '2021-09-20', 'nextDue' => '2022-03-20', 'status' => 'complete'],
            ['vaccine' => 'Polio', 'dose' => '1', 'dateGiven' => '2021-05-20', 'nextDue' => '2021-07-20', 'status' => 'complete'],
            ['vaccine' => 'Polio', 'dose' => '2', 'dateGiven' => '2021-07-20', 'nextDue' => '2021-09-20', 'status' => 'complete'],
            ['vaccine' => 'Polio', 'dose' => '3', 'dateGiven' => '2021-09-20', 'nextDue' => '2022-03-20', 'status' => 'complete'],
            ['vaccine' => 'MMR', 'dose' => '1', 'dateGiven' => '2023-05-10', 'nextDue' => '2028-05-10', 'status' => 'complete'],
            ['vaccine' => 'DTP Booster', 'dose' => '1', 'dateGiven' => '-', 'nextDue' => '2023-09-15', 'status' => 'upcoming'],
        ];

        return view('claude.children.show', compact('child', 'visits', 'immunizations'));
    }

    /**
     * Growth tracking index
     */
    public function growthIndex()
    {
        $data = [
            'normalNutritionCount' => 35,
            'normalNutritionPercent' => 83,
            'moderateMalnutritionCount' => 5,
            'moderateMalnutritionPercent' => 12,
            'severeMalnutritionCount' => 2,
            'severeMalnutritionPercent' => 5,
            'overweightCount' => 3,
            'overweightPercent' => 7,
            'children' => [
                ['id' => 1, 'name' => 'Emma Johnson', 'age' => '2 years 3 months', 'weight' => '12.5', 'height' => '87', 'bmi' => '16.5', 'weightForAge' => 0.5, 'heightForAge' => 0.3, 'bmiForAge' => 0.4, 'status' => 'normal'],
                ['id' => 2, 'name' => 'Oliver Smith', 'age' => '1 year 5 months', 'weight' => '9.8', 'height' => '76', 'bmi' => '16.9', 'weightForAge' => -1.8, 'heightForAge' => -1.2, 'bmiForAge' => -1.5, 'status' => 'moderate'],
                ['id' => 3, 'name' => 'Sophia Williams', 'age' => '3 years 1 month', 'weight' => '15.2', 'height' => '96', 'bmi' => '16.5', 'weightForAge' => 1.2, 'heightForAge' => 0.8, 'bmiForAge' => 0.9, 'status' => 'normal'],
                ['id' => 4, 'name' => 'Lucas Brown', 'age' => '4 years 6 months', 'weight' => '14.5', 'height' => '102', 'bmi' => '13.9', 'weightForAge' => -2.5, 'heightForAge' => -1.8, 'bmiForAge' => -2.3, 'status' => 'severe'],
                ['id' => 5, 'name' => 'Ava Davis', 'age' => '6 months', 'weight' => '7.8', 'height' => '68', 'bmi' => '16.9', 'weightForAge' => 0.8, 'heightForAge' => 0.5, 'bmiForAge' => 0.7, 'status' => 'normal'],
                ['id' => 6, 'name' => 'Noah Miller', 'age' => '2 years 8 months', 'weight' => '14.8', 'height' => '92', 'bmi' => '17.5', 'weightForAge' => 1.8, 'heightForAge' => 1.2, 'bmiForAge' => 1.5, 'status' => 'overweight'],
                ['id' => 7, 'name' => 'Mia Wilson', 'age' => '1 year 2 months', 'weight' => '8.5', 'height' => '72', 'bmi' => '16.4', 'weightForAge' => -1.2, 'heightForAge' => -0.8, 'bmiForAge' => -1.0, 'status' => 'moderate'],
                ['id' => 8, 'name' => 'Ethan Moore', 'age' => '3 years 5 months', 'weight' => '16.2', 'height' => '99', 'bmi' => '16.5', 'weightForAge' => 1.5, 'heightForAge' => 1.0, 'bmiForAge' => 1.2, 'status' => 'normal'],
            ],
        ];

        return view('claude.growth.index', $data);
    }

    /**
     * Immunizations index
     */
    public function immunizationsIndex()
    {
        $data = [
            'immunizations' => [
                ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'BCG', 'dose' => '1', 'dateGiven' => '2021-03-20', 'nextDue' => '-', 'status' => 'complete'],
                ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'Hepatitis B', 'dose' => '1', 'dateGiven' => '2021-03-20', 'nextDue' => '2021-04-20', 'status' => 'complete'],
                ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'Hepatitis B', 'dose' => '2', 'dateGiven' => '2021-04-20', 'nextDue' => '2021-10-20', 'status' => 'complete'],
                ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'Hepatitis B', 'dose' => '3', 'dateGiven' => '2021-10-20', 'nextDue' => '-', 'status' => 'complete'],
                ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'DTP', 'dose' => '1', 'dateGiven' => '2021-05-20', 'nextDue' => '2021-07-20', 'status' => 'complete'],
                ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'DTP', 'dose' => '2', 'dateGiven' => '2021-07-20', 'nextDue' => '2021-09-20', 'status' => 'complete'],
                ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'DTP', 'dose' => '3', 'dateGiven' => '2021-09-20', 'nextDue' => '2022-03-20', 'status' => 'complete'],
                ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'MMR', 'dose' => '1', 'dateGiven' => '2023-05-10', 'nextDue' => '2028-05-10', 'status' => 'complete'],
                ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'DTP Booster', 'dose' => '1', 'dateGiven' => '-', 'nextDue' => '2023-09-15', 'status' => 'upcoming'],
                ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'BCG', 'dose' => '1', 'dateGiven' => '2022-01-15', 'nextDue' => '-', 'status' => 'complete'],
                ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'Hepatitis B', 'dose' => '1', 'dateGiven' => '2022-01-15', 'nextDue' => '2022-02-15', 'status' => 'complete'],
                ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'MMR', 'dose' => '1', 'dateGiven' => '-', 'nextDue' => '2023-07-15', 'status' => 'due'],
            ],
            'dueThisWeek' => [
                ['childName' => 'Oliver Smith', 'vaccine' => 'MMR', 'dueDate' => '2023-07-15', 'daysUntil' => 2],
                ['childName' => 'Sophia Williams', 'vaccine' => 'DTP', 'dueDate' => '2023-07-16', 'daysUntil' => 3],
                ['childName' => 'Lucas Brown', 'vaccine' => 'Polio', 'dueDate' => '2023-07-18', 'daysUntil' => 5],
            ],
            'overdue' => [
                ['childName' => 'Lucas Brown', 'vaccine' => 'MMR', 'dueDate' => '2023-06-20', 'daysOverdue' => 25],
                ['childName' => 'Ava Davis', 'vaccine' => 'Hepatitis B', 'dueDate' => '2023-06-15', 'daysOverdue' => 30],
            ],
        ];

        return view('claude.immunizations.index', $data);
    }

    /**
     * Children index (placeholder for route)
     */
    public function childrenIndex()
    {
        return redirect()->route('growth.index');
    }

    /**
     * Edit child (placeholder)
     */
    public function editChild($id)
    {
        return redirect()->route('children.show', $id);
    }

    /**
     * Create growth measurement (placeholder)
     */
    public function createGrowth($id)
    {
        return redirect()->route('children.show', $id);
    }

    /**
     * Create visit (placeholder)
     */
    public function createVisit($id)
    {
        return redirect()->route('children.show', $id);
    }

    /**
     * Create immunization (placeholder)
     */
    public function createImmunization($id)
    {
        return redirect()->route('immunizations.index');
    }
}
