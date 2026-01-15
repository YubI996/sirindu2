@extends('layouts.health-app')

@section('title', $child['name'] ?? 'Child Profile')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <div class="d-flex align-items-center">
            <img src="https://picsum.photos/seed/child{{ $child['id'] ?? 1 }}/100/100.jpg" alt="{{ $child['name'] ?? 'Child' }}" class="child-avatar me-3" style="width: 100px; height: 100px;">
            <div>
                <h1 class="h3 mb-1">{{ $child['name'] ?? 'Emma Johnson' }}</h1>
                <p class="text-muted mb-0">ID: #{{ str_pad($child['id'] ?? 1, 5, '0', STR_PAD_LEFT) }}</p>
                <p class="text-muted">{{ $child['age'] ?? '2 years 3 months' }} old, {{ $child['gender'] ?? 'Female' }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group" role="group">
            <a href="{{ route('children.edit', $child['id'] ?? 1) }}" class="btn btn-outline-primary">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
            <a href="{{ route('growth.create', $child['id'] ?? 1) }}" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> Add Measurement
            </a>
            <button type="button" class="btn btn-outline-danger">
                <i class="fas fa-print me-1"></i> Print Report
            </button>
        </div>
    </div>
</div>

<div class="row">
    <!-- Child Information -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Child Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td class="text-muted">Date of Birth:</td>
                        <td>{{ \Carbon\Carbon::parse($child['dob'] ?? '2021-03-15')->format('F d, Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Age:</td>
                        <td>{{ $child['age'] ?? '2 years 3 months' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Gender:</td>
                        <td>{{ $child['gender'] ?? 'Female' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Blood Type:</td>
                        <td>{{ $child['bloodType'] ?? 'O+' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Allergies:</td>
                        <td>{{ $child['allergies'] ?? 'None' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Parent/Guardian Information -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Parent/Guardian</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td class="text-muted">Name:</td>
                        <td>{{ $child['parentName'] ?? 'Sarah Johnson' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Relationship:</td>
                        <td>{{ $child['relationship'] ?? 'Mother' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Contact:</td>
                        <td>{{ $child['contact'] ?? '+1 (555) 123-4567' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Address:</td>
                        <td>{{ $child['address'] ?? '123 Main St, City, State' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Current Health Status -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Current Health Status</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card h-100">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-2">Weight</h6>
                                <h3 class="mb-1">{{ $child['weight'] ?? '12.5' }} kg</h3>
                                <span class="badge bg-{{ $child['weightStatus'] ?? 'success' }}">
                                    {{ $child['weightStatusText'] ?? 'Normal' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card h-100">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-2">Height</h6>
                                <h3 class="mb-1">{{ $child['height'] ?? '87' }} cm</h3>
                                <span class="badge bg-{{ $child['heightStatus'] ?? 'success' }}">
                                    {{ $child['heightStatusText'] ?? 'Normal' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card h-100">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-2">BMI</h6>
                                <h3 class="mb-1">{{ $child['bmi'] ?? '16.5' }}</h3>
                                <span class="badge bg-{{ $child['bmiStatus'] ?? 'success' }}">
                                    {{ $child['bmiStatusText'] ?? 'Normal' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6 mb-3">
                        <h6>Nutritional Status (Z-Scores)</h6>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Weight for Age:</span>
                                <span class="z-score-indicator z-score-{{ $child['weightForAgeStatus'] ?? 'normal' }}"></span>
                                <span>{{ $child['weightForAgeZScore'] ?? '0.5' }}</span>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Height for Age:</span>
                                <span class="z-score-indicator z-score-{{ $child['heightForAgeStatus'] ?? 'normal' }}"></span>
                                <span>{{ $child['heightForAgeZScore'] ?? '0.3' }}</span>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Weight for Height:</span>
                                <span class="z-score-indicator z-score-{{ $child['weightForHeightStatus'] ?? 'normal' }}"></span>
                                <span>{{ $child['weightForHeightZScore'] ?? '0.7' }}</span>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>BMI for Age:</span>
                                <span class="z-score-indicator z-score-{{ $child['bmiForAgeStatus'] ?? 'normal' }}"></span>
                                <span>{{ $child['bmiForAgeZScore'] ?? '0.4' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6>Developmental Milestones</h6>
                        <div class="progress mb-2" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $child['motorSkills'] ?? '85' }}%" aria-valuenow="{{ $child['motorSkills'] ?? '85' }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted">Motor Skills: {{ $child['motorSkills'] ?? '85' }}%</small>
                        
                        <div class="progress mb-2" style="height: 10px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: {{ $child['language'] ?? '75' }}%" aria-valuenow="{{ $child['language'] ?? '75' }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted">Language: {{ $child['language'] ?? '75' }}%</small>
                        
                        <div class="progress mb-2" style="height: 10px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $child['social'] ?? '90' }}%" aria-valuenow="{{ $child['social'] ?? '90' }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted">Social Skills: {{ $child['social'] ?? '90' }}%</small>
                        
                        <div class="progress mb-2" style="height: 10px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $child['cognitive'] ?? '80' }}%" aria-valuenow="{{ $child['cognitive'] ?? '80' }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted">Cognitive: {{ $child['cognitive'] ?? '80' }}%</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Growth History -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Growth History</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary active" id="weightHistoryBtn">Weight</button>
                    <button type="button" class="btn btn-outline-primary" id="heightHistoryBtn">Height</button>
                    <button type="button" class="btn btn-outline-primary" id="bmiHistoryBtn">BMI</button>
                </div>
            </div>
            <div class="card-body">
                <div class="growth-chart-container">
                    <canvas id="growthHistoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Visits -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Visits</h5>
                <a href="{{ route('visits.create', $child['id'] ?? 1) }}" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            <div class="card-body">
                <div class="timeline">
                    @foreach($visits ?? [
                        ['date' => '2023-06-15', 'type' => 'Routine Checkup', 'notes' => 'Healthy development, all vitals normal'],
                        ['date' => '2023-05-10', 'type' => 'Vaccination', 'notes' => 'MMR vaccine administered'],
                        ['date' => '2023-04-05', 'type' => 'Sick Visit', 'notes' => 'Treated for common cold, recovered well'],
                        ['date' => '2023-03-15', 'type' => 'Routine Checkup', 'notes' => 'Growth parameters within normal range'],
                        ['date' => '2023-02-20', 'type' => 'Vaccination', 'notes' => 'DTP booster administered']
                    ] as $visit)
                    <div class="timeline-item mb-3">
                        <div class="d-flex">
                            <div class="me-3">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px;">
                                    <i class="fas fa-stethoscope"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $visit['type'] }}</h6>
                                <p class="mb-1 text-muted">{{ $visit['notes'] }}</p>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($visit['date'])->format('M d, Y') }}</small>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="text-center mt-3">
                    <a href="#" class="btn btn-sm btn-outline-primary">View All Visits</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Immunization Records -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Immunization Records</h5>
                <a href="{{ route('immunizations.create', $child['id'] ?? 1) }}" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus-circle me-1"></i> Add Immunization
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Vaccine</th>
                                <th>Dose</th>
                                <th>Date Given</th>
                                <th>Next Due</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($immunizations ?? [
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
                                ['vaccine' => 'DTP Booster', 'dose' => '1', 'dateGiven' => '-', 'nextDue' => '2023-09-15', 'status' => 'upcoming']
                            ] as $immunization)
                            <tr>
                                <td>{{ $immunization['vaccine'] }}</td>
                                <td>{{ $immunization['dose'] }}</td>
                                <td>{{ $immunization['dateGiven'] !== '-' ? \Carbon\Carbon::parse($immunization['dateGiven'])->format('M d, Y') : '-' }}</td>
                                <td>{{ $immunization['nextDue'] !== '-' ? \Carbon\Carbon::parse($immunization['nextDue'])->format('M d, Y') : '-' }}</td>
                                <td>
                                    @switch($immunization['status'])
                                        @case('complete')
                                            <span class="badge bg-success">Complete</span>
                                            @break
                                        @case('upcoming')
                                            <span class="badge bg-info">Upcoming</span>
                                            @break
                                        @case('due')
                                            <span class="badge bg-warning">Due</span>
                                            @break
                                        @case('overdue')
                                            <span class="badge bg-danger">Overdue</span>
                                            @break
                                    @endswitch
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Growth History Chart
    const growthCtx = document.getElementById('growthHistoryChart').getContext('2d');
    
    // Sample data for weight history
    const weightData = {
        labels: ['Mar 2021', 'Jun 2021', 'Sep 2021', 'Dec 2021', 'Mar 2022', 'Jun 2022', 'Sep 2022', 'Dec 2022', 'Mar 2023', 'Jun 2023'],
        datasets: [
            {
                label: 'Weight (kg)',
                data: [3.5, 5.8, 7.2, 8.5, 9.8, 10.5, 11.2, 11.8, 12.2, 12.5],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'WHO Median',
                data: [3.3, 5.9, 7.3, 8.6, 9.9, 10.6, 11.3, 11.9, 12.3, 12.6],
                borderColor: '#6c757d',
                borderDash: [5, 5],
                fill: false
            }
        ]
    };
    
    // Sample data for height history
    const heightData = {
        labels: ['Mar 2021', 'Jun 2021', 'Sep 2021', 'Dec 2021', 'Mar 2022', 'Jun 2022', 'Sep 2022', 'Dec 2022', 'Mar 2023', 'Jun 2023'],
        datasets: [
            {
                label: 'Height (cm)',
                data: [50, 62, 68, 73, 77, 80, 83, 85, 86, 87],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'WHO Median',
                data: [49.9, 62.5, 68.5, 73.5, 77.5, 80.5, 83.5, 85.5, 86.5, 87.5],
                borderColor: '#6c757d',
                borderDash: [5, 5],
                fill: false
            }
        ]
    };
    
    // Sample data for BMI history
    const bmiData = {
        labels: ['Mar 2021', 'Jun 2021', 'Sep 2021', 'Dec 2021', 'Mar 2022', 'Jun 2022', 'Sep 2022', 'Dec 2022', 'Mar 2023', 'Jun 2023'],
        datasets: [
            {
                label: 'BMI',
                data: [14.0, 15.1, 15.6, 15.9, 16.5, 16.4, 16.2, 16.3, 16.5, 16.5],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'WHO Median',
                data: [13.4, 15.0, 15.5, 15.8, 16.3, 16.2, 16.0, 16.1, 16.3, 16.3],
                borderColor: '#6c757d',
                borderDash: [5, 5],
                fill: false
            }
        ]
    };
    
    // Initialize chart with weight data
    let growthChart = new Chart(growthCtx, {
        type: 'line',
        data: weightData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Weight History'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Weight (kg)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });
    
    // Button event listeners to switch between data
    document.getElementById('weightHistoryBtn').addEventListener('click', function() {
        // Update active button
        document.querySelectorAll('#weightHistoryBtn, #heightHistoryBtn, #bmiHistoryBtn').forEach(btn => {
            btn.classList.remove('active');
        });
        this.classList.add('active');
        
        // Update chart data
        growthChart.data = weightData;
        growthChart.options.plugins.title.text = 'Weight History';
        growthChart.options.scales.y.title.text = 'Weight (kg)';
        growthChart.update();
    });
    
    document.getElementById('heightHistoryBtn').addEventListener('click', function() {
        // Update active button
        document.querySelectorAll('#weightHistoryBtn, #heightHistoryBtn, #bmiHistoryBtn').forEach(btn => {
            btn.classList.remove('active');
        });
        this.classList.add('active');
        
        // Update chart data
        growthChart.data = heightData;
        growthChart.options.plugins.title.text = 'Height History';
        growthChart.options.scales.y.title.text = 'Height (cm)';
        growthChart.update();
    });
    
    document.getElementById('bmiHistoryBtn').addEventListener('click', function() {
        // Update active button
        document.querySelectorAll('#weightHistoryBtn, #heightHistoryBtn, #bmiHistoryBtn').forEach(btn => {
            btn.classList.remove('active');
        });
        this.classList.add('active');
        
        // Update chart data
        growthChart.data = bmiData;
        growthChart.options.plugins.title.text = 'BMI History';
        growthChart.options.scales.y.title.text = 'BMI (kg/m²)';
        growthChart.update();
    });
});
</script>
@endpush