// Global JavaScript functions for the Health App

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});

// Function to calculate age from date of birth
function calculateAge(dob) {
    const today = new Date();
    const birthDate = new Date(dob);
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    // Calculate months
    let monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0) {
        monthDiff += 12;
    }
    
    // Calculate days
    let dayDiff = today.getDate() - birthDate.getDate();
    if (dayDiff < 0) {
        const lastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
        dayDiff += lastMonth.getDate();
        monthDiff--;
    }
    
    if (monthDiff < 0) {
        monthDiff += 12;
        age--;
    }
    
    if (age > 0) {
        return `${age} year${age > 1 ? 's' : ''} ${monthDiff > 0 ? monthDiff + ' month' + (monthDiff > 1 ? 's' : '') : ''}`;
    } else {
        return `${monthDiff} month${monthDiff > 1 ? 's' : ''} ${dayDiff > 0 ? dayDiff + ' day' + (dayDiff > 1 ? 's' : '') : ''}`;
    }
}

// Function to calculate BMI
function calculateBMI(weight, height) {
    const heightInMeters = height / 100;
    return (weight / (heightInMeters * heightInMeters)).toFixed(1);
}

// Function to determine nutritional status based on Z-score
function getNutritionalStatus(zScore) {
    if (zScore < -3) {
        return { status: 'severe', text: 'Severe Malnutrition', color: 'danger' };
    } else if (zScore < -2) {
        return { status: 'moderate', text: 'Moderate Malnutrition', color: 'warning' };
    } else if (zScore < -1) {
        return { status: 'mild', text: 'Mild Malnutrition', color: 'warning' };
    } else if (zScore < 1) {
        return { status: 'normal', text: 'Normal', color: 'success' };
    } else if (zScore < 2) {
        return { status: 'overweight', text: 'Overweight', color: 'info' };
    } else {
        return { status: 'obese', text: 'Obese', color: 'danger' };
    }
}

// Function to format date
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// Function to initialize date pickers with today's date as default
function initializeDatePickers() {
    const today = new Date().toISOString().split('T')[0];
    const dateInputs = document.querySelectorAll('input[type="date"]');
    
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = today;
        }
    });
}

// Initialize date pickers when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeDatePickers();
});

// Function to show confirmation dialog
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Function to show notification
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to document
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

// Function to validate form
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Function to show loading spinner
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = `
            <div class="spinner-container">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
    }
}

// Function to hide loading spinner
function hideLoading(elementId, content) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = content;
    }
}

// Function to export data to CSV
function exportToCSV(data, filename) {
    let csv = '';
    
    // Get headers
    const headers = Object.keys(data[0]);
    csv += headers.join(',') + '\n';
    
    // Get rows
    data.forEach(row => {
        const values = headers.map(header => {
            const value = row[header];
            return typeof value === 'string' && value.includes(',') 
                ? `"${value}"` 
                : value;
        });
        csv += values.join(',') + '\n';
    });
    
    // Create download link
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', filename);
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Function to print element
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Print</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { padding: 20px; }
                    .no-print { display: none; }
                    @media print {
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                ${element.innerHTML}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}