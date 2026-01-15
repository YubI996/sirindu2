@extends('layouts.health-app')

@section('title', 'Dashboard')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h1 class="h3">Health Dashboard</h1>
        <p class="text-muted">Overview of children's health status and upcoming activities</p>
    </div>
    <div class="col-md-6 text-end">
        <button class="btn btn-primary btn-health" data-bs-toggle="modal" data-bs-target="#addChildModal">
            <i class="fas fa-plus-circle me-2"></i>Add New Child
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Children</h6>
                        <h3 class="mb-0">{{ $childrenCount ?? 42 }}</h3>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-child fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Normal Nutrition</h6>
                        <h3 class="mb-0">{{ $normalNutritionCount ?? 35 }}</h3>
                    </div>
                    <div class="text-success">
                        <i class="fas fa-apple-alt fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Vaccinations Due</h6>
                        <h3 class="mb-0">{{ $vaccinationsDueCount ?? 8 }}</h3>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-syringe fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card danger h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Malnutrition Cases</h6>
                        <h3 class="mb-0">{{ $malnutritionCount ?? 7 }}</h3>
                    </div>
                    <div class="text-danger">
                        <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Children -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Children</h5>
                <a href="{{ route('children.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Child</th>
                                <th>Age</th>
                                <th>Last Visit</th>
                                <th>Nutritional Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentChildren ?? [
                                ['id' => 1, 'name' => 'Emma Johnson', 'age' => '2 years 3 months', 'lastVisit' => '2023-06-15', 'zScore' => 0.5, 'status' => 'normal'],
                                ['id' => 2, 'name' => 'Oliver Smith', 'age' => '1 year 5 months', 'lastVisit' => '2023-06-10', 'zScore' => -1.8, 'status' => 'moderate'],
                                ['id' => 3, 'name' => 'Sophia Williams', 'age' => '3 years 1 month', 'lastVisit' => '2023-06-05', 'zScore' => 1.2, 'status' => 'normal'],
                                ['id' => 4, 'name' => 'Lucas Brown', 'age' => '4 years 6 months', 'lastVisit' => '2023-05-28', 'zScore' => -2.5, 'status' => 'severe'],
                                ['id' => 5, 'name' => 'Ava Davis', 'age' => '6 months', 'lastVisit' => '2023-06-12', 'zScore' => 0.8, 'status' => 'normal']
                            ] as $child)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://picsum.photos/seed/child{{ $child['id'] }}/50/50.jpg" alt="{{ $child['name'] }}" class="child-avatar me-2">
                                        <div>
                                            <div class="fw-medium">{{ $child['name'] }}</div>
                                            <small class="text-muted">ID: #{{ str_pad($child['id'], 5, '0', STR_PAD_LEFT) }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $child['age'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($child['lastVisit'])->format('M d, Y') }}</td>
                                <td>
                                    <span class="z-score-indicator z-score-{{ $child['status'] }}"></span>
                                    Z-score: {{ $child['zScore'] }}
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('children.show', $child['id']) }}" class="btn btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('children.edit', $child['id']) }}" class="btn btn-outline-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
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

    <!-- Upcoming Immunizations -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Upcoming Immunizations</h5>
                <a href="{{ route('immunizations.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    @foreach($upcomingImmunizations ?? [
                        ['childName' => 'Emma Johnson', 'vaccine' => 'MMR', 'dueDate' => '2023-07-20', 'status' => 'upcoming'],
                        ['childName' => 'Oliver Smith', 'vaccine' => 'DTP', 'dueDate' => '2023-07-15', 'status' => 'due'],
                        ['childName' => 'Sophia Williams', 'vaccine' => 'Hepatitis B', 'dueDate' => '2023-07-10', 'status' => 'overdue'],
                        ['childName' => 'Lucas Brown', 'vaccine' => 'Polio', 'dueDate' => '2023-07-25', 'status' => 'upcoming'],
                        ['childName' => 'Ava Davis', 'vaccine' => 'BCG', 'dueDate' => '2023-07-05', 'status' => 'due']
                    ] as $immunization)
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $immunization['vaccine'] }}</h6>
                                <p class="mb-1">{{ $immunization['childName'] }}</p>
                                <small class="text-muted">Due: {{ \Carbon\Carbon::parse($immunization['dueDate'])->format('M d, Y') }}</small>
                            </div>
                            <div>
                                <span class="immunization-status immunization-{{ $immunization['status'] }}" title="{{ ucfirst($immunization['status']) }}"></span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Growth Chart -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Growth Trends</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" id="growthTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="weight-tab" data-bs-toggle="tab" data-bs-target="#weight" type="button" role="tab">Weight for Age</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="height-tab" data-bs-toggle="tab" data-bs-target="#height" type="button" role="tab">Height for Age</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="bmi-tab" data-bs-toggle="tab" data-bs-target="#bmi" type="button" role="tab">BMI for Age</button>
                    </li>
                </ul>
                <div class="tab-content" id="growthTabContent">
                    <div class="tab-pane fade show active" id="weight" role="tabpanel">
                        <div class="growth-chart-container">
                            <canvas id="weightChart"></canvas>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="height" role="tabpanel">
                        <div class="growth-chart-container">
                            <canvas id="heightChart"></canvas>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="bmi" role="tabpanel">
                        <div class="growth-chart-container">
                            <canvas id="bmiChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Child Modal -->
<div class="modal fade" id="addChildModal" tabindex="-1" aria-labelledby="addChildModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addChildModalLabel">Add New Child</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="mb-3">
                        <label for="childName" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="childName" placeholder="Enter child's full name">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="childDOB" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="childDOB">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="childGender" class="form-label">Gender</label>
                            <select class="form-select" id="childGender">
                                <option value="" selected disabled>Select gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="parentName" class="form-label">Parent/Guardian Name</label>
                        <input type="text" class="form-control" id="parentName" placeholder="Enter parent/guardian name">
                    </div>
                    <div class="mb-3">
                        <label for="parentContact" class="form-label">Contact Number</label>
                        <input type="tel" class="form-control" id="parentContact" placeholder="Enter contact number">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Save Child</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Weight for Age Chart
    const weightCtx = document.getElementById('weightChart').getContext('2d');
    const weightChart = new Chart(weightCtx, {
        type: 'line',
        data: {
            labels: ['0', '6', '12', '18', '24', '30', '36', '42', '48', '54', '60'],
            datasets: [
                {
                    label: 'Child Weight (kg)',
                    data: [3.5, 7.2, 9.5, 11.0, 12.1, 13.2, 14.3, 15.0, 15.8, 16.5, 17.2],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'WHO Median',
                    data: [3.3, 7.5, 9.8, 11.2, 12.3, 13.4, 14.5, 15.2, 16.0, 16.7, 17.4],
                    borderColor: '#6c757d',
                    borderDash: [5, 5],
                    fill: false
                },
                {
                    label: 'WHO -2 SD',
                    data: [2.8, 6.4, 8.4, 9.6, 10.5, 11.4, 12.3, 12.9, 13.6, 14.2, 14.8],
                    borderColor: '#ffc107',
                    borderDash: [3, 3],
                    fill: false
                },
                {
                    label: 'WHO -3 SD',
                    data: [2.5, 5.8, 7.6, 8.6, 9.4, 10.2, 11.0, 11.5, 12.1, 12.6, 13.1],
                    borderColor: '#dc3545',
                    borderDash: [3, 3],
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Weight for Age Z-Scores (WHO Standards)'
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
                        text: 'Age (months)'
                    }
                }
            }
        }
    });

    // Height for Age Chart
    const heightCtx = document.getElementById('heightChart').getContext('2d');
    const heightChart = new Chart(heightCtx, {
        type: 'line',
        data: {
            labels: ['0', '6', '12', '18', '24', '30', '36', '42', '48', '54', '60'],
            datasets: [
                {
                    label: 'Child Height (cm)',
                    data: [50, 68, 76, 82, 87, 91, 95, 98, 101, 104, 107],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'WHO Median',
                    data: [49.9, 67.6, 75.7, 82.3, 87.1, 91.2, 95.1, 98.5, 101.6, 104.5, 107.3],
                    borderColor: '#6c757d',
                    borderDash: [5, 5],
                    fill: false
                },
                {
                    label: 'WHO -2 SD',
                    data: [47.4, 64.5, 72.0, 78.1, 82.6, 86.4, 90.0, 93.1, 96.0, 98.7, 101.3],
                    borderColor: '#ffc107',
                    borderDash: [3, 3],
                    fill: false
                },
                {
                    label: 'WHO -3 SD',
                    data: [45.6, 62.2, 69.2, 75.0, 79.2, 82.8, 86.2, 89.1, 91.8, 94.4, 96.9],
                    borderColor: '#dc3545',
                    borderDash: [3, 3],
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Height for Age Z-Scores (WHO Standards)'
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
                        text: 'Height (cm)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Age (months)'
                    }
                }
            }
        }
    });

    // BMI for Age Chart
    const bmiCtx = document.getElementById('bmiChart').getContext('2d');
    const bmiChart = new Chart(bmiCtx, {
        type: 'line',
        data: {
            labels: ['0', '6', '12', '18', '24', '30', '36', '42', '48', '54', '60'],
            datasets: [
                {
                    label: 'Child BMI',
                    data: [14.0, 15.6, 16.4, 16.3, 16.0, 15.9, 15.8, 15.6, 15.5, 15.2, 15.0],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'WHO Median',
                    data: [13.4, 16.2, 16.8, 16.5, 16.2, 16.0, 15.9, 15.7, 15.6, 15.4, 15.2],
                    borderColor: '#6c757d',
                    borderDash: [5, 5],
                    fill: false
                },
                {
                    label: 'WHO -2 SD',
                    data: [11.9, 14.2, 14.7, 14.5, 14.3, 14.1, 14.0, 13.9, 13.8, 13.6, 13.5],
                    borderColor: '#ffc107',
                    borderDash: [3, 3],
                    fill: false
                },
                {
                    label: 'WHO -3 SD',
                    data: [11.0, 13.1, 13.5, 13.3, 13.1, 13.0, 12.9, 12.8, 12.7, 12.6, 12.5],
                    borderColor: '#dc3545',
                    borderDash: [3, 3],
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'BMI for Age Z-Scores (WHO Standards)'
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
                        text: 'BMI (kg/m²)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Age (months)'
                    }
                }
            }
        }
    });
});
</script>
@endpush