@extends('admin::layouts.app')
@section('title')
Admin — SIRINDU
@endsection
@section('title-content')
User
@endsection
@section('item')
User
@endsection
@section('item-active')
Data User
@endsection
@section('content')
@if($errors->any())
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    @foreach ($errors->all() as $error)
    <ul>
        <li> <strong>{{ $error }}</strong></li>
    </ul>
    @endforeach
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
@endif
<button data-toggle="modal" data-target="#createUserModal" type="button" class="btn btn-success">Create</button>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">NIK</th>
                <th scope="col">Nama</th>
                <th scope="col">Email</th>
                <th scope="col">Type</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
            @php
            $no = 1;
            @endphp
            @forelse ($user as $data)
            <tr>
                <th scope="row">{{$no++}}</th>
                <th scope="row">{{$data->nik}}</th>
                <th scope="row">{{$data->name}}</th>
                <th scope="row">{{$data->email}}</th>
                <th scope="row">
                    @if ($data->role === 'superadmin')
                    <span class="badge badge-info">Super Admin</span>
                    @elseif ($data->role === 'surveilans_puskesmas')
                    <span class="badge badge-success">Surveilans – Puskesmas</span>
                    @elseif ($data->role === 'surveilans_rs')
                    <span class="badge badge-warning">Surveilans – RS</span>
                    @elseif ($data->role === 'imunisasi_faskes')
                    <span class="badge badge-primary">Imunisasi Faskes</span>
                    @else
                    <span class="badge badge-secondary">{{ $data->role ?? 'Legacy (type='.$data->type.')' }}</span>
                    @endif
                </th>
                <th scope="row">
                    <a href="{{route('super.admin.editUser',$data->id)}}" class="btn btn-warning">Edit</a>
                    <button data-toggle="modal" data-target="#confirmationModal{{$data->id}}" type="button" class="btn btn-danger">Delete</button>
                    @include('admin.user.delete-confirmation')
                </th>
            </tr>
            @empty
            <tr>
                <th colspan="6">
                    <center>
                        Data Not Found
                    </center>
                </th>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('admin.user.create')
@endsection
@section('custom_scripts')
<script type="text/javascript">
     $(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $('#kec').on('change', function() {
            var id = $(this).val();
            $.ajax({
                    url: '{{url("admin/get-kel-dasar-anak")}}' + '/' + id,
                    success: function(response) {
                        $('#kel').empty();

                        $.each(response, function(id, name) {
                            $('#kel').append(new Option(name, id))
                        })
                    }
                }),
                $.ajax({
                    url: '{{url("admin/get-puskesmas-dasar-anak")}}' + '/' + id,
                    success: function(response) {
                        $('#puskesmas').empty();

                        $.each(response, function(id, name) {
                            $('#puskesmas').append(new Option(name, id))
                        })
                    }
                })
        });

        $('#puskesmas').on('change', function() {
            var id = $(this).val();
            $.ajax({
                url: '{{url("admin/get-posyandu-dasar-anak")}}' + '/' + id,
                success: function(response) {
                    $('#posyandu').empty();

                    $.each(response, function(id, name) {
                        $('#posyandu').append(new Option(name, id))
                    })
                }
            })
        });
    });
    
   
</script>
@endsection