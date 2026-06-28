<style>
.header-title-css{
	display: flex;
  align-items: stretch;
  justify-content: space-between;
  position: relative;
  padding: 0 25px;
  border-bottom: 1px solid #ebedf2;
  min-height: 60px;
  border-top-left-radius: 4px;
  border-top-right-radius: 4px;
  box-shadow: 0px 0px 13px 0px rgba(82, 63, 105, 0.05);
  background-color: #ffffff;
  margin-bottom: 20px;
  border-radius: 4px;
  font-variant:small-caps;
}
</style>
@props([
	'title'
])
<div class="col-12">
		                        <h3 class="kt-portlet__head-title head-title header-title-css text-primary d-flex align-items-center ">{{ $title }}</h3>
							</div>
