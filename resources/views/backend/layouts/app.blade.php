<!DOCTYPE html>
@langrtl
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
@else
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@endlangrtl

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', app_name())</title>
    <meta name="description" content="@yield('meta_description', 'Laravel Starter')">
    <meta name="author" content="@yield('meta_author', 'FasTrax Infotech')">
    @yield('meta')

    <!-- Check if the language is set to RTL, so apply the RTL layouts -->
    <!-- Otherwise apply the normal LTR layouts -->
    {{ style(mix('css/backend.css')) }}

    <style>
        .hidden {
            display: none !important;
        }
    </style>
</head>

<body class="app header-fixed sidebar-fixed aside-menu-off-canvas sidebar-lg-show">
    @include('backend.includes.header')

    <div class="app-body">
        @include('backend.includes.sidebar')

        <main class="main">
            @include('includes.partials.read-only')
            @include('includes.partials.logged-in-as')
            {!! Breadcrumbs::render() !!}

            <div class="container-fluid">
                <div class="animated fadeIn">
                    <div class="content-header">
                        @yield('page-header')
                    </div>
                    <!--content-header-->

                    @include('includes.partials.messages')
                    @yield('content')
                </div>
                <!--animated-->
            </div>
            <!--container-fluid-->
        </main>
        <!--main-->

        @include('backend.includes.aside')
    </div>
    <!--app-body-->

    @include('backend.includes.footer')

    <!--Start  JS Scripts ================================== -->
    <!-- datatables.full.min Includeds following libraries:
        Bootstrap 5 5.0.1, jQuery 3 3.6.0, JSZip 2.5.0, pdfmake 0.1.36, DataTables 1.11.3,
        AutoFill 2.3.7, Buttons 2.0.1, Column visibility 2.0.1, HTML5 export 2.0.1, Print view 2.0.1,
        ColReorder 1.5.5, DateTime 1.1.1, FixedColumns 4.0.1, FixedHeader 3.2.0, KeyTable 2.6.4,
        Responsive 2.2.9, RowGroup 1.1.4, RowReorder 1.2.8, Scroller 2.0.5, SearchBuilder 1.3.0,
        SearchPanes 1.4.0, Select 1.3.3 -->
    {{ script('js/datatables.full.min.js') }}

    <!-- JS Scripts in vendor folder be compiled in vendor.js -->
    {{-- script(mix('js/vendor.js')) --}}

    <!-- JS Scripts (Vue or React) in resources folder be compiled in frontend.js -->
    {{-- script(mix('js/backend.js')) --}}

    <!-- includes js files which determine specifications of table-->
    @isset($js)
        @foreach($js as $j)
            {!! script(asset('js/backend/'. $j. '.js')) !!}
        @endforeach
    @endif

    <!-- includes js from blade pages -->
    @yield('pagescript')
    <!-- END JS Scripts ================================== -->
</body>

</html>