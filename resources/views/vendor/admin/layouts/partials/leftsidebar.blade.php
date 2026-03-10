<div class="left-side-bar">
	<div class="brand-logo">
		<a href="#">
			<!-- <img src="{{asset('logo/Sirindu-white.png')}}" alt="" class="light-logo"> -->
			<img src="{{asset('logo/Sirindu-allblack.png')}}" alt="" class="dark-logo">
			<img src="{{asset('logo/Sirindu-white.png')}}" alt="" class="light-logo">
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
				     Juga mencakup legacy super-admin (type=0)
				     ============================================ --}}
				@if (Auth::user()->isSuperAdmin())
				<li>
					<a href="{{route('admin.home')}}" class="dropdown-toggle no-arrow">
						<span class="micon fa fa-home"></span><span class="mtext">Home</span>
					</a>
				</li>
				{{-- Dashboard Imunisasi --}}
				<li class="dropdown">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon fa fa-chart-pie"></span><span class="mtext">Dashboard</span>
					</a>
					<ul class="submenu">
						<li><a href="{{route('admin.analytics')}}"><i class="fa fa-chart-bar mr-2"></i>Analytics</a></li>
						<li><a href="{{route('admin.map')}}"><i class="fa fa-map-marked-alt mr-2"></i>Peta Sebaran</a></li>
						<li><a href="{{route('admin.earlyWarning')}}"><i class="fa fa-chart-line mr-2"></i>Proyeksi</a></li>
					</ul>
				</li>
				{{-- Epidemiologi --}}
				<li class="dropdown">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon fa fa-virus"></span><span class="mtext">Epidemiologi</span>
					</a>
					<ul class="submenu">
						<li><a href="{{route('admin.epidemiologi.dashboard')}}"><i class="fa fa-chart-line mr-2"></i>Dashboard Analytics</a></li>
						<li><a href="{{route('admin.epidemiologi.map')}}"><i class="fa fa-map-marked-alt mr-2"></i>Peta Sebaran</a></li>
						<li><a href="{{route('admin.epidemiologi.index')}}"><i class="fa fa-list mr-2"></i>Daftar Kasus</a></li>
						<li><a href="{{route('admin.epidemiologi.create')}}"><i class="fa fa-plus mr-2"></i>Tambah Kasus</a></li>
						<li><a href="{{route('admin.masterdata.penyakit.index')}}"><i class="fa fa-disease mr-2"></i>Jenis Penyakit</a></li>
					</ul>
				</li>
				{{-- Data --}}
				<li class="dropdown">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon fa fa-database"></span><span class="mtext">Data</span>
					</a>
					<ul class="submenu">
						<li><a href="{{route('admin.anak')}}">Data Anak</a></li>
						<li><a href="{{route('admin.masterdata.vaksin.index')}}"><i class="fa fa-syringe mr-2"></i>Jenis Vaksin</a></li>
					</ul>
				</li>
				{{-- User Management --}}
				<li>
					<a href="{{route('super.admin.user')}}" class="dropdown-toggle no-arrow">
						<span class="micon fa fa-user"></span><span class="mtext">User</span>
					</a>
				</li>

				{{-- ============================================
				     FASKES SURVEILANS (Puskesmas / RS)
				     Hanya modul Epidemiologi, scoped ke faskes
				     ============================================ --}}
				@elseif (Auth::user()->isFaskesSurveilans())
				<li>
					<a href="{{route('admin.epidemiologi.dashboard')}}" class="dropdown-toggle no-arrow">
						<span class="micon fa fa-home"></span><span class="mtext">Home</span>
					</a>
				</li>
				<li class="dropdown">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon fa fa-virus"></span><span class="mtext">Epidemiologi</span>
					</a>
					<ul class="submenu">
						<li><a href="{{route('admin.epidemiologi.dashboard')}}"><i class="fa fa-chart-line mr-2"></i>Dashboard Analytics</a></li>
						<li><a href="{{route('admin.epidemiologi.map')}}"><i class="fa fa-map-marked-alt mr-2"></i>Peta Sebaran</a></li>
						<li><a href="{{route('admin.epidemiologi.index')}}"><i class="fa fa-list mr-2"></i>Daftar Kasus</a></li>
						<li><a href="{{route('admin.epidemiologi.create')}}"><i class="fa fa-plus mr-2"></i>Tambah Kasus</a></li>
					</ul>
				</li>

				{{-- ============================================
				     LEGACY ADMIN (type=1) & IMUNISASI FASKES
				     Modul imunisasi: Dashboard + Data Anak
				     ============================================ --}}
				@else
				<li>
					<a href="{{route('admin.home')}}" class="dropdown-toggle no-arrow">
						<span class="micon fa fa-home"></span><span class="mtext">Home</span>
					</a>
				</li>
				<li class="dropdown">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon fa fa-chart-pie"></span><span class="mtext">Dashboard</span>
					</a>
					<ul class="submenu">
						<li><a href="{{route('admin.analytics')}}"><i class="fa fa-chart-bar mr-2"></i>Analytics</a></li>
						<li><a href="{{route('admin.map')}}"><i class="fa fa-map-marked-alt mr-2"></i>Peta Sebaran</a></li>
						<li><a href="{{route('admin.earlyWarning')}}"><i class="fa fa-chart-line mr-2"></i>Proyeksi</a></li>
					</ul>
				</li>
				<li class="dropdown">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon fa fa-database"></span><span class="mtext">Data</span>
					</a>
					<ul class="submenu">
						<li><a href="{{route('admin.anak')}}">Data Anak</a></li>
					</ul>
				</li>
				@endif

			</ul>
		</div>
	</div>
</div>
<div class="mobile-menu-overlay"></div>
