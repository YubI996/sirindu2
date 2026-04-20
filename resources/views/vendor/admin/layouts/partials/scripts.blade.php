<script src="{{asset('admin/vendors/scripts/core.js')}}"></script>
<script src="{{asset('admin/vendors/scripts/script.min.js')}}"></script>
<script src="{{asset('admin/vendors/scripts/process.js')}}"></script>
<script src="{{asset('admin/vendors/scripts/layout-settings.js')}}"></script>
<!-- <script src="{{asset('admin/src/plugins/apexcharts/apexcharts.min.js')}}"></script> -->
<script src="{{asset('admin/vendors/scripts/sweetalert2.min.js')}}"></script>
@include('sweetalert::alert')
@stack('js')
@yield('custom_scripts')