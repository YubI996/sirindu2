<div class="left-side-bar">
	<div class="brand-logo">
		<a href="{{ route('admin.home') }}" aria-label="Beranda SIRINDU">
			<img src="{{asset('logo/Sirindu-allblack.png')}}" alt="SIRINDU" class="dark-logo">
			<img src="{{asset('logo/Sirindu-white.png')}}" alt="SIRINDU" class="light-logo">
		</a>
		<div class="close-sidebar" data-toggle="left-sidebar-close">
			<i class="ion-close-round"></i>
		</div>
	</div>
	<div class="menu-block customscroll">
		<div class="sidebar-menu">
			<ul id="accordion-menu">

				{{-- ============================================
				     SUPERADMIN (Dinkes) — Akses penuh semua modul
				     ============================================ --}}
				@if (Auth::user()->isSuperAdmin())

				@php
					$dashboard = request()->routeIs('admin.analytics', 'admin.map', 'admin.earlyWarning', 'admin.epidemiologi.dashboard', 'admin.epidemiologi.map', 'admin.pd3i.dashboard', 'admin.timbang.*', 'admin.home', 'super.admin.home');
					$anak = request()->routeIs('admin.anak', 'admin.anak.*');
					$pd3i = request()->routeIs('admin.epidemiologi.index', 'admin.epidemiologi.create', 'admin.epidemiologi.show', 'admin.epidemiologi.edit');
					$export = request()->routeIs('admin.export.*');
					$import = request()->routeIs('admin.importCsv.*');
					$master = request()->routeIs('admin.masterdata.*');
					$admin = request()->routeIs('super.admin.user', 'super.admin.storeUser', 'super.admin.editUser', 'super.admin.updateUser', 'super.admin.destroyUser');
				@endphp

				<li class="dropdown section-group {{ $dashboard ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $dashboard ? 'active' : '' }}">
						<span class="micon fa fa-tachometer"></span><span class="mtext">Dashboard</span>
					</a>
					<ul class="submenu" {!! $dashboard ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('admin.analytics')}}" class="{{ request()->routeIs('admin.analytics') ? 'active' : '' }}">Imunisasi</a></li>
						<li><a href="{{route('admin.timbang.dashboard')}}" class="{{ request()->routeIs('admin.timbang.*') ? 'active' : '' }}">Gizi &amp; Timbang</a></li>
						<li><a href="{{route('admin.pd3i.dashboard')}}" class="{{ request()->routeIs('admin.pd3i.dashboard') ? 'active' : '' }}">Surveilans PD3I</a></li>
						<li><a href="{{route('admin.epidemiologi.dashboard')}}" class="{{ request()->routeIs('admin.epidemiologi.dashboard') ? 'active' : '' }}">Surveilans (legacy)</a></li>
						<li><a href="{{route('admin.map')}}" class="{{ request()->routeIs('admin.map') ? 'active' : '' }}">Peta Statistik</a></li>
						<li><a href="{{route('admin.epidemiologi.map')}}" class="{{ request()->routeIs('admin.epidemiologi.map') ? 'active' : '' }}">Peta Sebaran</a></li>
						<li><a href="{{route('admin.earlyWarning')}}" class="{{ request()->routeIs('admin.earlyWarning') ? 'active' : '' }}">Proyeksi</a></li>
					</ul>
				</li>

				<li class="dropdown section-group {{ $anak ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $anak ? 'active' : '' }}">
						<span class="micon fa fa-child"></span><span class="mtext">Anak</span>
					</a>
					<ul class="submenu" {!! $anak ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('admin.anak')}}" class="{{ request()->routeIs('admin.anak', 'admin.anak.*') ? 'active' : '' }}">Data Anak</a></li>
					</ul>
				</li>

				<li class="dropdown section-group {{ $pd3i ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $pd3i ? 'active' : '' }}">
						<span class="micon fa fa-clipboard-list"></span><span class="mtext">PD3I</span>
					</a>
					<ul class="submenu" {!! $pd3i ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('admin.epidemiologi.index')}}" class="{{ request()->routeIs('admin.epidemiologi.index', 'admin.epidemiologi.show', 'admin.epidemiologi.edit') ? 'active' : '' }}">Daftar Kasus</a></li>
						<li><a href="{{route('admin.epidemiologi.create')}}" class="{{ request()->routeIs('admin.epidemiologi.create') ? 'active' : '' }}">Tambah Kasus</a></li>
					</ul>
				</li>

				<li class="dropdown section-group {{ $export ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $export ? 'active' : '' }}">
						<span class="micon fa fa-file-export"></span><span class="mtext">Export Data</span>
					</a>
					<ul class="submenu" {!! $export ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('admin.export.imunisasi.index')}}" class="{{ request()->routeIs('admin.export.imunisasi.*') ? 'active' : '' }}">Export Imunisasi</a></li>
					</ul>
				</li>

				<li class="dropdown section-group {{ $import ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $import ? 'active' : '' }}">
						<span class="micon fa fa-file-upload"></span><span class="mtext">Import Data</span>
					</a>
					<ul class="submenu" {!! $import ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('admin.importCsv.index')}}" class="{{ request()->routeIs('admin.importCsv.*') ? 'active' : '' }}">Import CSV</a></li>
					</ul>
				</li>

				<li class="dropdown section-group {{ $master ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $master ? 'active' : '' }}">
						<span class="micon fa fa-cogs"></span><span class="mtext">Data Master</span>
					</a>
					<ul class="submenu" {!! $master ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('admin.masterdata.vaksin.index')}}" class="{{ request()->routeIs('admin.masterdata.vaksin.*') ? 'active' : '' }}">Antigen</a></li>
						<li><a href="{{route('admin.masterdata.penyakit.index')}}" class="{{ request()->routeIs('admin.masterdata.penyakit.*') ? 'active' : '' }}">Surveilans PD3I</a></li>
						<li><a href="{{route('admin.masterdata.penduduk.index')}}" class="{{ request()->routeIs('admin.masterdata.penduduk.*') ? 'active' : '' }}">Jumlah Penduduk</a></li>
					</ul>
				</li>

				<li class="dropdown section-group {{ $admin ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $admin ? 'active' : '' }}">
						<span class="micon fa fa-user-shield"></span><span class="mtext">Administrasi</span>
					</a>
					<ul class="submenu" {!! $admin ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('super.admin.user')}}" class="{{ request()->routeIs('super.admin.*') ? 'active' : '' }}">Pengguna</a></li>
					</ul>
				</li>

				{{-- ============================================
				     FASKES SURVEILANS (Puskesmas / RS)
				     ============================================ --}}
				@elseif (Auth::user()->isFaskesSurveilans())

				@php
					$dashboard = request()->routeIs('admin.epidemiologi.dashboard', 'admin.epidemiologi.map', 'admin.home');
					$pd3i = request()->routeIs('admin.epidemiologi.index', 'admin.epidemiologi.create', 'admin.epidemiologi.show', 'admin.epidemiologi.edit');
				@endphp

				<li class="dropdown section-group {{ $dashboard ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $dashboard ? 'active' : '' }}">
						<span class="micon fa fa-tachometer"></span><span class="mtext">Dashboard</span>
					</a>
					<ul class="submenu" {!! $dashboard ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('admin.epidemiologi.dashboard')}}" class="{{ request()->routeIs('admin.epidemiologi.dashboard') ? 'active' : '' }}">Surveilans</a></li>
						<li><a href="{{route('admin.epidemiologi.map')}}" class="{{ request()->routeIs('admin.epidemiologi.map') ? 'active' : '' }}">Peta Sebaran</a></li>
					</ul>
				</li>

				<li class="dropdown section-group {{ $pd3i ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $pd3i ? 'active' : '' }}">
						<span class="micon fa fa-clipboard-list"></span><span class="mtext">PD3I</span>
					</a>
					<ul class="submenu" {!! $pd3i ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('admin.epidemiologi.index')}}" class="{{ request()->routeIs('admin.epidemiologi.index', 'admin.epidemiologi.show', 'admin.epidemiologi.edit') ? 'active' : '' }}">Daftar Kasus</a></li>
						<li><a href="{{route('admin.epidemiologi.create')}}" class="{{ request()->routeIs('admin.epidemiologi.create') ? 'active' : '' }}">Tambah Kasus</a></li>
					</ul>
				</li>

				{{-- ============================================
				     LEGACY ADMIN & IMUNISASI FASKES
				     ============================================ --}}
				@else

				@php
					$dashboard = request()->routeIs('admin.analytics', 'admin.map', 'admin.earlyWarning', 'admin.home');
					$anak = request()->routeIs('admin.anak', 'admin.anak.*');
					$export = request()->routeIs('admin.export.*');
				@endphp

				<li class="dropdown section-group {{ $dashboard ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $dashboard ? 'active' : '' }}">
						<span class="micon fa fa-tachometer"></span><span class="mtext">Dashboard</span>
					</a>
					<ul class="submenu" {!! $dashboard ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('admin.analytics')}}" class="{{ request()->routeIs('admin.analytics') ? 'active' : '' }}">Imunisasi</a></li>
						<li><a href="{{route('admin.map')}}" class="{{ request()->routeIs('admin.map') ? 'active' : '' }}">Peta Statistik</a></li>
						<li><a href="{{route('admin.earlyWarning')}}" class="{{ request()->routeIs('admin.earlyWarning') ? 'active' : '' }}">Proyeksi</a></li>
					</ul>
				</li>

				<li class="dropdown section-group {{ $anak ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $anak ? 'active' : '' }}">
						<span class="micon fa fa-child"></span><span class="mtext">Anak</span>
					</a>
					<ul class="submenu" {!! $anak ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('admin.anak')}}" class="{{ request()->routeIs('admin.anak', 'admin.anak.*') ? 'active' : '' }}">Data Anak</a></li>
					</ul>
				</li>

				<li class="dropdown section-group {{ $export ? 'show' : '' }}">
					<a href="javascript:;" class="dropdown-toggle section-toggle {{ $export ? 'active' : '' }}">
						<span class="micon fa fa-file-export"></span><span class="mtext">Export Data</span>
					</a>
					<ul class="submenu" {!! $export ? 'style="display:block;"' : '' !!}>
						<li><a href="{{route('admin.export.imunisasi.index')}}" class="{{ request()->routeIs('admin.export.imunisasi.*') ? 'active' : '' }}">Export Imunisasi</a></li>
					</ul>
				</li>
				@endif

			</ul>
		</div>
	</div>
</div>
<div class="mobile-menu-overlay"></div>
