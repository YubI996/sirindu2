@extends('layouts.health-app')

@section('title', 'Immunization Schedule')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h1 class="h3">Immunization Schedule</h1>
        <p class="text-muted">Manage and track vaccination records for all children</p>
    </div>
    <div class="col-md-6 text-end">
        <button class="btn btn-primary btn-health" data-bs-toggle="modal" data-bs-target="#scheduleVaccineModal">
            <i class="fas fa-plus-circle me-2"></i>Schedule Vaccination
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
                <label for="vaccineFilter" class="form-label">Filter by Vaccine</label>
                <select class="form-select" id="vaccineFilter">
                    <option value="" selected>All Vaccines</option>
                    <option value="bcg">BCG</option>
                    <option value="hepb">Hepatitis B</option>
                    <option value="dtp">DTP</option>
                    <option value="polio">Polio</option>
                    <option value="mmr">MMR</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="statusFilter" class="form-label">Filter by Status</label>
                <select class="form-select" id="statusFilter">
                    <option value="" selected>All Statuses</option>
                    <option value="complete">Complete</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="due">Due</option>
                    <option value="overdue">Overdue</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Immunization Calendar View -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Immunization Calendar</h5>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-primary active" id="calendarViewBtn">Calendar</button>
            <button type="button" class="btn btn-outline-primary" id="listViewBtn">List</button>
        </div>
    </div>
    <div class="card-body">
        <div id="calendarView">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Vaccine</th>
                            <th>Birth</th>
                            <th>6 Weeks</th>
                            <th>10 Weeks</th>
                            <th>14 Weeks</th>
                            <th>9 Months</th>
                            <th>12-15 Months</th>
                            <th>18-24 Months</th>
                            <th>5 Years</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>BCG</td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Hepatitis B</td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>DTP</td>
                            <td></td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td></td>
                            <td></td>
                            <td class="text-center">
                                <i class="fas fa-exclamation-circle text-warning" title="Due"></i>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-clock text-info" title="Upcoming"></i>
                            </td>
                        </tr>
                        <tr>
                            <td>Polio</td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td></td>
                            <td></td>
                            <td class="text-center">
                                <i class="fas fa-exclamation-circle text-warning" title="Due"></i>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-clock text-info" title="Upcoming"></i>
                            </td>
                        </tr>
                        <tr>
                            <td>Measles</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>MMR</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="text-center">
                                <i class="fas fa-check-circle text-success" title="Given"></i>
                            </td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="listView" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Child</th>
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
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'BCG', 'dose' => '1', 'dateGiven' => '2021-03-20', 'nextDue' => '-', 'status' => 'complete'],
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'Hepatitis B', 'dose' => '1', 'dateGiven' => '2021-03-20', 'nextDue' => '2021-04-20', 'status' => 'complete'],
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'Hepatitis B', 'dose' => '2', 'dateGiven' => '2021-04-20', 'nextDue' => '2021-10-20', 'status' => 'complete'],
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'Hepatitis B', 'dose' => '3', 'dateGiven' => '2021-10-20', 'nextDue' => '-', 'status' => 'complete'],
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'DTP', 'dose' => '1', 'dateGiven' => '2021-05-20', 'nextDue' => '2021-07-20', 'status' => 'complete'],
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'DTP', 'dose' => '2', 'dateGiven' => '2021-07-20', 'nextDue' => '2021-09-20', 'status' => 'complete'],
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'DTP', 'dose' => '3', 'dateGiven' => '2021-09-20', 'nextDue' => '2022-03-20', 'status' => 'complete'],
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'Polio', 'dose' => '1', 'dateGiven' => '2021-05-20', 'nextDue' => '2021-07-20', 'status' => 'complete'],
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'Polio', 'dose' => '2', 'dateGiven' => '2021-07-20', 'nextDue' => '2021-09-20', 'status' => 'complete'],
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'Polio', 'dose' => '3', 'dateGiven' => '2021-09-20', 'nextDue' => '2022-03-20', 'status' => 'complete'],
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'MMR', 'dose' => '1', 'dateGiven' => '2023-05-10', 'nextDue' => '2028-05-10', 'status' => 'complete'],
                            ['childName' => 'Emma Johnson', 'childId' => 1, 'vaccine' => 'DTP Booster', 'dose' => '1', 'dateGiven' => '-', 'nextDue' => '2023-09-15', 'status' => 'upcoming'],
                            ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'BCG', 'dose' => '1', 'dateGiven' => '2022-01-15', 'nextDue' => '-', 'status' => 'complete'],
                            ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'Hepatitis B', 'dose' => '1', 'dateGiven' => '2022-01-15', 'nextDue' => '2022-02-15', 'status' => 'complete'],
                            ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'Hepatitis B', 'dose' => '2', 'dateGiven' => '2022-02-15', 'nextDue' => '2022-08-15', 'status' => 'complete'],
                            ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'Hepatitis B', 'dose' => '3', 'dateGiven' => '2022-08-15', 'nextDue' => '-', 'status' => 'complete'],
                            ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'DTP', 'dose' => '1', 'dateGiven' => '2022-02-25', 'nextDue' => '2022-04-25', 'status' => 'complete'],
                            ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'DTP', 'dose' => '2', 'dateGiven' => '2022-04-25', 'nextDue' => '2022-06-25', 'status' => 'complete'],
                            ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'DTP', 'dose' => '3', 'dateGiven' => '2022-06-25', 'nextDue' => '2023-01-25', 'status' => 'complete'],
                            ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'Polio', 'dose' => '1', 'dateGiven' => '2022-02-25', 'nextDue' => '2022-04-25', 'status' => 'complete'],
                            ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'Polio', 'dose' => '2', 'dateGiven' => '2022-04-25', 'nextDue' => '2022-06-25', 'status' => 'complete'],
                            ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'Polio', 'dose' => '3', 'dateGiven' => '2022-06-25', 'nextDue' => '2023-01-25', 'status' => 'complete'],
                            ['childName' => 'Oliver Smith', 'childId' => 2, 'vaccine' => 'MMR', 'dose' => '1', 'dateGiven' => '-', 'nextDue' => '2023-07-15', 'status' => 'due']
                        ] as $immunization)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="https://picsum.photos/seed/child{{ $immunization['childId'] }}/40/40.jpg" alt="{{ $immunization['childName'] }}" class="child-avatar me-2" style="width: 40px; height: 40px;">
                                    <div>
                                        <div class="fw-medium">{{ $immunization['childName'] }}</div>
                                        <small class="text-muted">ID: #{{ str_pad($immunization['childId'], 5, '0', STR_PAD_LEFT) }}</small>
                                    </div>
                                </div>
                            </td>
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

<!-- Upcoming Vaccinations -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Vaccinations Due This Week</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    @foreach($dueThisWeek ?? [
                        ['childName' => 'Oliver Smith', 'vaccine' => 'MMR', 'dueDate' => '2023-07-15', 'daysUntil' => 2],
                        ['childName' => 'Sophia Williams', 'vaccine' => 'DTP', 'dueDate' => '2023-07-16', 'daysUntil' => 3],
                        ['childName' => 'Lucas Brown', 'vaccine' => 'Polio', 'dueDate' => '2023-07-18', 'daysUntil' => 5]
                    ] as $vaccination)
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $vaccination['vaccine'] }}</h6>
                                <p class="mb-1">{{ $vaccination['childName'] }}</p>
                                <small class="text-muted">Due: {{ \Carbon\Carbon::parse($vaccination['dueDate'])->format('M d, Y') }}</small>
                            </div>
                            <div>
                                <span class="badge bg-warning">In {{ $vaccination['daysUntil'] }} days</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Overdue Vaccinations</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    @foreach($overdue ?? [
                        ['childName' => 'Lucas Brown', 'vaccine' => 'MMR', 'dueDate' => '2023-06-20', 'daysOverdue' => 25],
                        ['childName' => 'Ava Davis', 'vaccine' => 'Hepatitis B', 'dueDate' => '2023-06-15', 'daysOverdue' => 30]
                    ] as $vaccination)
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $vaccination['vaccine'] }}</h6>
                                <p class="mb-1">{{ $vaccination['childName'] }}</p>
                                <small class="text-muted">Was due: {{ \Carbon\Carbon::parse($vaccination['dueDate'])->format('M d, Y') }}</small>
                            </div>
                            <div>
                                <span class="badge bg-danger">{{ $vaccination['daysOverdue'] }} days overdue</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Vaccine Modal -->
<div class="modal fade" id="scheduleVaccineModal" tabindex="-1" aria-labelledby="scheduleVaccineModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleVaccineModalLabel">Schedule Vaccination</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="mb-3">
                        <label for="vaccineChild" class="form-label">Child</label>
                        <select class="form-select" id="vaccineChild">
                            <option value="" selected disabled>Select child</option>
                            <option value="1">Emma Johnson</option>
                            <option value="2">Oliver Smith</option>
                            <option value="3">Sophia Williams</option>
                            <option value="4">Lucas Brown</option>
                            <option value="5">Ava Davis</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="vaccineType" class="form-label">Vaccine</label>
                        <select class="form-select" id="vaccineType">
                            <option value="" selected disabled>Select vaccine</option>
                            <option value="bcg">BCG</option>
                            <option value="hepb">Hepatitis B</option>
                            <option value="dtp">DTP</option>
                            <option value="polio">Polio</option>
                            <option value="mmr">MMR</option>
                            <option value="measles">Measles</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="vaccineDose" class="form-label">Dose</label>
                        <input type="number" class="form-control" id="vaccineDose" min="1" value="1">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="vaccineDate" class="form-label">Date Given</label>
                            <input type="date" class="form-control" id="vaccineDate">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="vaccineNextDue" class="form-label">Next Due Date</label>
                            <input type="date" class="form-control" id="vaccineNextDue">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="vaccineNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="vaccineNotes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Schedule Vaccination</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle between calendar and list view
    document.getElementById('calendarViewBtn').addEventListener('click', function() {
        document.getElementById('calendarView').style.display = 'block';
        document.getElementById('listView').style.display = 'none';
        this.classList.add('active');
        document.getElementById('listViewBtn').classList.remove('active');
    });
    
    document.getElementById('listViewBtn').addEventListener('click', function() {
        document.getElementById('calendarView').style.display = 'none';
        document.getElementById('listView').style.display = 'block';
        this.classList.add('active');
        document.getElementById('calendarViewBtn').classList.remove('active');
    });
    
    // Filter functionality
    function filterTable() {
        const childFilter = document.getElementById('childFilter').value;
        const vaccineFilter = document.getElementById('vaccineFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        
        // This would typically make an AJAX request to filter the data
        // For demo purposes, we'll just show an alert
        console.log('Filtering by:', { childFilter, vaccineFilter, statusFilter });
    }
    
    document.getElementById('childFilter').addEventListener('change', filterTable);
    document.getElementById('vaccineFilter').addEventListener('change', filterTable);
    document.getElementById('statusFilter').addEventListener('change', filterTable);
});
</script>
@endpush