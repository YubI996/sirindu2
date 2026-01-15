@extends('layouts.health-app')

@section('title', 'Growth Tracking')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h1 class="h3">Growth Tracking</h1>
        <p class="text-muted">Monitor children's growth patterns and nutritional status</p>
    </div>
    <div class="col-md-6 text-end">
        <button class="btn btn-primary btn-health" data-bs-toggle="modal" data-bs-target="#addMeasurementModal">
            <i class="fas fa-plus-circle me-2"></i>Add Measurement
        </button>
    </div>
</div>

<!-- Filter and Search -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="childFilter" class="form-label">Filter by Child</label>
                <select class="form-select" id="childFilter">
                    <option value="" selected>All Children</option>
                    <option value="1">Emma Johnson</option>
                    <option value="2">Oliver Smith</option>
                    <option value="3">Sophia Williams</option>
                    <option value="4">Lucas Brown</option>
                    <option value="5">Ava Davis</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="ageRangeFilter" class="form-label">Age Range</label>
                <select class="form-select" id="ageRangeFilter">
                    <option value="" selected>All Ages</option>
                    <option value="0-6">0-6 months</option>
                    <option value="6-12">6-12 months</option>
                    <option value="12-24">1-2 years</option>
                    <option value="24-60">2-5 years</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="statusFilter" class="form-label">Nutritional Status</label>
                <select class="form-select" id="statusFilter">
                    <option value="" selected>All Statuses</option>
                    <option value="normal">Normal</option>
                    <option value="moderate">Moderate Malnutrition</option>
                    <option value="severe">Severe Malnutrition</option>
                    <option value="overweight">Overweight</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Growth Statistics -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Normal Nutrition</h6>
                        <h3 class="mb-0">{{ $normalNutritionCount ?? 35 }}</h3>
                        <small class="text-success">{{ $normalNutritionPercent ?? 83 }}%</small>
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
                        <h6 class="text-muted mb-2">Moderate Malnutrition</h6>
                        <h3 class="mb-0">{{ $moderateMalnutritionCount ?? 5 }}</h3>
                        <small class="text-warning">{{ $moderateMalnutritionPercent ?? 12 }}%</small>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
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
                        <h6 class="text-muted mb-2">Severe Malnutrition</h6>
                        <h3 class="mb-0">{{ $severeMalnutritionCount ?? 2 }}</h3>
                        <small class="text-danger">{{ $severeMalnutritionPercent ?? 5 }}%</small>
                    </div>
                    <div class="text-danger">
                        <i class="fas fa-times-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card info h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Overweight</h6>
                        <h3 class="mb-0">{{ $overweightCount ?? 3 }}</h3>
                        <small class="text-info">{{ $overweightPercent ?? 7 }}%</small>
                    </div>
                    <div class="text-info">
                        <i class="fas fa-weight fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Growth Charts -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Growth Distribution</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <h6>Weight for Age Z-Scores</h6>
                        <div class="growth-chart-container">
                            <canvas id="weightForAgeChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6>Height for Age Z-Scores</h6>
                        <div class="growth-chart-container">
                            <canvas id="heightForAgeChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6>Weight for Height Z-Scores</h6>
                        <div class="growth-chart-container">
                            <canvas id="weightForHeightChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6>BMI for Age Z-Scores</h6>
                        <div class="growth-chart-container">
                            <canvas id="bmiForAgeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Children List with Growth Status -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Children Growth Status</h5>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-primary active" id="tableViewBtn">Table</button>
            <button type="button" class="btn btn-outline-primary" id="cardViewBtn">Cards</button>
        </div>
    </div>
    <div class="card-body">
        <!-- Table View -->
        <div id="tableView">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Child</th>
                            <th>Age</th>
                            <th>Weight</th>
                            <th>Height</th>
                            <th>BMI</th>
                            <th>Weight/Age Z-Score</th>
                            <th>Height/Age Z-Score</th>
                            <th>BMI/Age Z-Score</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($children ?? [
                            ['id' => 1, 'name' => 'Emma Johnson', 'age' => '2 years 3 months', 'weight' => '12.5', 'height' => '87', 'bmi' => '16.5', 'weightForAge' => 0.5, 'heightForAge' => 0.3, 'bmiForAge' => 0.4, 'status' => 'normal'],
                            ['id' => 2, 'name' => 'Oliver Smith', 'age' => '1 year 5 months', 'weight' => '9.8', 'height' => '76', 'bmi' => '16.9', 'weightForAge' => -1.8, 'heightForAge' => -1.2, 'bmiForAge' => -1.5, 'status' => 'moderate'],
                            ['id' => 3, 'name' => 'Sophia Williams', 'age' => '3 years 1 month', 'weight' => '15.2', 'height' => '96', 'bmi' => '16.5', 'weightForAge' => 1.2, 'heightForAge' => 0.8, 'bmiForAge' => 0.9, 'status' => 'normal'],
                            ['id' => 4, 'name' => 'Lucas Brown', 'age' => '4 years 6 months', 'weight' => '14.5', 'height' => '102', 'bmi' => '13.9', 'weightForAge' => -2.5, 'heightForAge' => -1.8, 'bmiForAge' => -2.3, 'status' => 'severe'],
                            ['id' => 5, 'name' => 'Ava Davis', 'age' => '6 months', 'weight' => '7.8', 'height' => '68', 'bmi' => '16.9', 'weightForAge' => 0.8, 'heightForAge' => 0.5, 'bmiForAge' => 0.7, 'status' => 'normal'],
                            ['id' => 6, 'name' => 'Noah Miller', 'age' => '2 years 8 months', 'weight' => '14.8', 'height' => '92', 'bmi' => '17.5', 'weightForAge' => 1.8, 'heightForAge' => 1.2, 'bmiForAge' => 1.5, 'status' => 'overweight'],
                            ['id' => 7, 'name' => 'Mia Wilson', 'age' => '1 year 2 months', 'weight' => '8.5', 'height' => '72', 'bmi' => '16.4', 'weightForAge' => -1.2, 'heightForAge' => -0.8, 'bmiForAge' => -1.0, 'status' => 'moderate'],
                            ['id' => 8, 'name' => 'Ethan Moore', 'age' => '3 years 5 months', 'weight' => '16.2', 'height' => '99', 'bmi' => '16.5', 'weightForAge' => 1.5, 'heightForAge' => 1.0, 'bmiForAge' => 1.2, 'status' => 'normal']
                        ] as $child)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="https://picsum.photos/seed/child{{ $child['id'] }}/40/40.jpg" alt="{{ $child['name'] }}" class="child-avatar me-2" style="width: 40px; height: 40px;">
                                    <div>
                                        <div class="fw-medium">{{ $child['name'] }}</div>
                                        <small class="text-muted">ID: #{{ str_pad($child['id'], 5, '0', STR_PAD_LEFT) }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $child['age'] }}</td>
                            <td>{{ $child['weight'] }} kg</td>
                            <td>{{ $child['height'] }} cm</td>
                            <td>{{ $child['bmi'] }}</td>
                            <td>
                                <span class="z-score-indicator z-score-{{ $child['weightForAge'] > -2 ? ($child['weightForAge'] > -1 ? 'normal' : 'moderate') : 'severe' }}"></span>
                                {{ $child['weightForAge'] }}
                            </td>
                            <td>
                                <span class="z-score-indicator z-score-{{ $child['heightForAge'] > -2 ? ($child['heightForAge'] > -1 ? 'normal' : 'moderate') : 'severe' }}"></span>
                                {{ $child['heightForAge'] }}
                            </td>
                            <td>
                                <span class="z-score-indicator z-score-{{ $child['bmiForAge'] > -2 ? ($child['bmiForAge'] > -1 ? 'normal' : 'moderate') : 'severe' }}"></span>
                                {{ $child['bmiForAge'] }}
                            </td>
                            <td>
                                @switch($child['status'])
                                    @case('normal')
                                        <span class="badge bg-success">Normal</span>
                                        @break
                                    @case('moderate')
                                        <span class="badge bg-warning">Moderate</span>
                                        @break
                                    @case('severe')
                                        <span class="badge bg-danger">Severe</span>
                                        @break
                                    @case('overweight')
                                        <span class="badge bg-info">Overweight</span>
                                        @break
                                @endswitch
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('children.show', $child['id']) }}" class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('growth.create', $child['id']) }}" class="btn btn-outline-success">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Card View -->
        <div id="cardView" style="display: none;">
            <div class="row">
                @foreach($children ?? [
                    ['id' => 1, 'name' => 'Emma Johnson', 'age' => '2 years 3 months', 'weight' => '12.5', 'height' => '87', 'bmi' => '16.5', 'weightForAge' => 0.5, 'heightForAge' => 0.3, 'bmiForAge' => 0.4, 'status' => 'normal'],
                    ['id' => 2, 'name' => 'Oliver Smith', 'age' => '1 year 5 months', 'weight' => '9.8', 'height' => '76', 'bmi' => '16.9', 'weightForAge' => -1.8, 'heightForAge' => -1.2, 'bmiForAge' => -1.5, 'status' => 'moderate'],
                    ['id' => 3, 'name' => 'Sophia Williams', 'age' => '3 years 1 month', 'weight' => '15.2', 'height' => '96', 'bmi' => '16.5', 'weightForAge' => 1.2, 'heightForAge' => 0.8, 'bmiForAge' => 0.9, 'status' => 'normal'],
                    ['id' => 4, 'name' => 'Lucas Brown', 'age' => '4 years 6 months', 'weight' => '14.5', 'height' => '102', 'bmi' => '13.9', 'weightForAge' => -2.5, 'heightForAge' => -1.8, 'bmiForAge' => -2.3, 'status' => 'severe'],
                    ['id' => 5, 'name' => 'Ava Davis', 'age' => '6 months', 'weight' => '7.8', 'height' => '68', 'bmi' => '16.9', 'weightForAge' => 0.8, 'heightForAge' => 0.5, 'bmiForAge' => 0.7, 'status' => 'normal'],
                    ['id' => 6, 'name' => 'Noah Miller', 'age' => '2 years 8 months', 'weight' => '14.8', 'height' => '92', 'bmi' => '17.5', 'weightForAge' => 1.8, 'heightForAge' => 1.2, 'bmiForAge' => 1.5, 'status' => 'overweight'],
                    ['id' => 7, 'name' => 'Mia Wilson', 'age' => '1 year 2 months', 'weight' => '8.5', 'height' => '72', 'bmi' => '16.4', 'weightForAge' => -1.2, 'heightForAge' => -0.8, 'bmiForAge' => -1.0, 'status' => 'moderate'],
                    ['id' => 8, 'name' => 'Ethan Moore', 'age' => '3 years 5 months', 'weight' => '16.2', 'height' => '99', 'bmi' => '16.5', 'weightForAge' => 1.5, 'heightForAge' => 1.0, 'bmiForAge' => 1.2, 'status' => 'normal']
                ] as $child)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <img src="https://picsum.photos/seed/child{{ $child['id'] }}/60/60.jpg" alt="{{ $child['name'] }}" class="child-avatar me-3" style="width: 60px; height: 60px;">
                                <div>
                                    <h6 class="mb-0">{{ $child['name'] }}</h6>
                                    <small class="text-muted">{{ $child['age'] }}</small>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <small class="text-muted">Weight:</small>
                                    <div>{{ $child['weight'] }} kg</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Height:</small>
                                    <div>{{ $child['height'] }} cm</div>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <small class="text-muted">BMI:</small>
                                    <div>{{ $child['bmi'] }}</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Status:</small>
                                    <div>
                                        @switch($child['status'])
                                            @case('normal')
                                                <span class="badge bg-success">Normal</span>
                                                @break
                                            @case('moderate')
                                                <span class="badge bg-warning">Moderate</span>
                                                @break
                                            @case('severe')
                                                <span class="badge bg-danger">Severe</span>
                                                @break
                                            @case('overweight')
                                                <span class="badge bg-info">Overweight</span>
                                                @break
                                        @endswitch
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-3">
                                <a href="{{ route('children.show', $child['id']) }}" class="btn btn-sm btn-outline-primary">View Details</a>
                                <a href="{{ route('growth.create', $child['id']) }}" class="btn btn-sm btn-outline-success">Add Measurement</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- Add Measurement Modal -->
<div class="modal fade" id="addMeasurementModal" tabindex="-1" aria-labelledby="addMeasurementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMeasurementModalLabel">Add Growth Measurement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="mb-3">
                        <label for="measurementChild" class="form-label">Child</label>
                        <select class="form-select" id="measurementChild">
                            <option value="" selected disabled>Select child</option>
                            <option value="1">Emma Johnson</option>
                            <option value="2">Oliver Smith</option>
                            <option value="3">Sophia Williams</option>
                            <option value="4">Lucas Brown</option>
                            <option value="5">Ava Davis</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="measurementDate" class="form-label">Measurement Date</label>
                        <input type="date" class="form-control" id="measurementDate">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="measurementWeight" class="form-label">Weight (kg)</label>
                            <input type="number" class="form-control" id="measurementWeight" step="0.1" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="measurementHeight" class="form-label">Height (cm)</label>
                            <input type="number" class="form-control" id="measurementHeight" step="0.1" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="measurementHeadCirc" class="form-label">Head Circumference (cm)</label>
                            <input type="number" class="form-control" id="measurementHeadCirc" step="0.1" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="measurementMUAC" class="form-label">MUAC (cm)</label>
                            <input type="number" class="form-control" id="measurementMUAC" step="0.1" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="measurementNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="measurementNotes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Save Measurement</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle between table and card view
    document.getElementById('tableViewBtn').addEventListener('click', function() {
        document.getElementById('tableView').style.display = 'block';
        document.getElementById('cardView').style.display = 'none';
        this.classList.add('active');
        document.getElementById('cardViewBtn').classList.remove('active');
    });
    
    document.getElementById('cardViewBtn').addEventListener('click', function() {
        document.getElementById('tableView').style.display = 'none';
        document.getElementById('cardView').style.display = 'block';
        this.classList.add('active');
        document.getElementById('tableViewBtn').classList.remove('active');
    });
    
    // Weight for Age Distribution Chart
    const weightForAgeCtx = document.getElementById('weightForAgeChart').getContext('2d');
    const weightForAgeChart = new Chart(weightForAgeCtx, {
        type: 'bar',
        data: {
            labels: ['<-3 SD', '-3 to -2 SD', '-2 to -1 SD', '-1 to 0 SD', '0 to 1 SD', '1 to 2 SD', '>2 SD'],
            datasets: [{
                label: 'Number of Children',
                data: [2, 3, 5, 15, 12, 4, 1],
                backgroundColor: [
                    '#dc3545', // Severe
                    '#dc3545', // Severe
                    '#ffc107', // Moderate
                    '#198754', // Normal
                    '#198754', // Normal
                    '#0dcaf0', // Overweight
                    '#0dcaf0'  // Overweight
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Weight for Age Z-Score Distribution'
                },
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Children'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Z-Score Range'
                    }
                }
            }
        }
    });
    
    // Height for Age Distribution Chart
    const heightForAgeCtx = document.getElementById('heightForAgeChart').getContext('2d');
    const heightForAgeChart = new Chart(heightForAgeCtx, {
        type: 'bar',
        data: {
            labels: ['<-3 SD', '-3 to -2 SD', '-2 to -1 SD', '-1 to 0 SD', '0 to 1 SD', '1 to 2 SD', '>2 SD'],
            datasets: [{
                label: 'Number of Children',
                data: [1, 2, 4, 18, 10, 6, 1],
                backgroundColor: [
                    '#dc3545', // Severe
                    '#dc3545', // Severe
                    '#ffc107', // Moderate
                    '#198754', // Normal
                    '#198754', // Normal
                    '#0dcaf0', // Overweight
                    '#0dcaf0'  // Overweight
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Height for Age Z-Score Distribution'
                },
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Children'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Z-Score Range'
                    }
                }
            }
        }
    });
    
    // Weight for Height Distribution Chart
    const weightForHeightCtx = document.getElementById('weightForHeightChart').getContext('2d');
    const weightForHeightChart = new Chart(weightForHeightCtx, {
        type: 'bar',
        data: {
            labels: ['<-3 SD', '-3 to -2 SD', '-2 to -1 SD', '-1 to 0 SD', '0 to 1 SD', '1 to 2 SD', '>2 SD'],
            datasets: [{
                label: 'Number of Children',
                data: [1, 2, 3, 16, 14, 5, 1],
                backgroundColor: [
                    '#dc3545', // Severe
                    '#dc3545', // Severe
                    '#ffc107', // Moderate
                    '#198754', // Normal
                    '#198754', // Normal
                    '#0dcaf0', // Overweight
                    '#0dcaf0'  // Overweight
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Weight for Height Z-Score Distribution'
                },
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Children'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Z-Score Range'
                    }
                }
            }
        }
    });
    
    // BMI for Age Distribution Chart
    const bmiForAgeCtx = document.getElementById('bmiForAgeChart').getContext('2d');
    const bmiForAgeChart = new Chart(bmiForAgeCtx, {
        type: 'bar',
        data: {
            labels: ['<-3 SD', '-3 to -2 SD', '-2 to -1 SD', '-1 to 0 SD', '0 to 1 SD', '1 to 2 SD', '>2 SD'],
            datasets: [{
                label: 'Number of Children',
                data: [1, 1, 4, 17, 13, 5, 1],
                backgroundColor: [
                    '#dc3545', // Severe
                    '#dc3545', // Severe
                    '#ffc107', // Moderate
                    '#198754', // Normal
                    '#198754', // Normal
                    '#0dcaf0', // Overweight
                    '#0dcaf0'  // Overweight
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'BMI for Age Z-Score Distribution'
                },
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Children'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Z-Score Range'
                    }
                }
            }
        }
    });
});
</script>
@endpush