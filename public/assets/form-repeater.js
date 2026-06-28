//== Class definition
var FormRepeater = function () {

	//== Private functions
	var demo1 = function () {
		$('#m_repeater_1').repeater({
			initEmpty: false,
			defaultValues: {
				'exchange_rate':1
			},
			show: function () {
				$('select.repeater-select').selectpicker('refresh')
				$(this).slideDown()
				appendNewOptionsToAllSelects(this)

			},
			isFirstItemUndeletable: true,

			hide: function (deleteElement) {
				//    $(this).addClass('will-be-hidden');
				if ($('#first-loading').length) {
					$(this).hide(deleteElement, function () {
						deleteElement()
					})
				}
				else {
					if (confirm('Are you sure you want to delete this element?')) {
						$(this).slideUp(deleteElement, function () {
							deleteElement()
							$('select.main-service-item').trigger('change')
						})
					}



				}
			},
			isFirstItemUndeletable: true
		})
	}
	// m_repeater_2

	var appendNewOptionsToAllSelects = function (currentRepeaterItem) {
	
		if ($('[data-modal-title]').length) {
			
			let currentSelect = $(currentRepeaterItem).find('select').attr('data-modal-name')
			let modalType = $(currentRepeaterItem).find('select').attr('data-modal-type')
			let selects = {}
			$('select[data-modal-name="' + currentSelect + '"][data-modal-type="' + modalType + '"] option').each(function (index, option) {
				selects[$(option).attr('value')] = $(option).html()
			})

			$('select[data-modal-name="' + currentSelect + '"][data-modal-type="' + modalType + '"]').each(function (index, select) {
				var selectedValue = $(select).val()
				var currentOptions = ''
				var currentOptionsValue = []
				$(select).find('option').each(function (index, option) {
					var currentOption = $(option).attr('value')
					var isCurrentSelected = currentOption == selectedValue ? 'selected' : ''
					currentOptions += '<option value="' + currentOption + '" ' + isCurrentSelected + ' > ' + $(option).html() + ' </option>'
					currentOptionsValue.push(currentOption)
				})
				for (var allOptionValue in selects) {
					if (!currentOptionsValue.includes(allOptionValue)) {
						var isCurrentSelected = false
						currentOptions += '<option value="' + allOptionValue + '" ' + isCurrentSelected + ' > ' + selects[allOptionValue] + ' </option>'
						currentOptionsValue.push(allOptionValue)
					}
				}
				$(select).empty().append(currentOptions).selectpicker('refresh').trigger('change')

			})
		}
	}

	var demo2 = function () {
		$('#m_repeater_2').repeater({
			initEmpty: false,

			defaultValues: {
				'exchange_rate':1
			},
			isFirstItemUndeletable: true,
			show: function () {
				$('.main-service-item').trigger('change')

				$(this).slideDown()
				appendNewOptionsToAllSelects(this)
			},

			hide: function (deleteElement) {
				if ($('#first-loading').length) {
					$(this).slideUp(deleteElement, function () {

						deleteElement()
						//   $('select.main-service-item').trigger('change');
					})
				}
				else {
					if (confirm('Are you sure you want to delete this element?')) {
						$(this).slideUp(deleteElement, function () {

							deleteElement()
							$('select.main-service-item').trigger('change')
						})
					}
				}

			}
		})
	}


	var demo3 = function () {
		$('#m_repeater_3').repeater({
			initEmpty: false,

			defaultValues: {
				'exchange_rate':1
			},
			isFirstItemUndeletable: true,

			show: function () {
				$(this).slideDown()
				appendNewOptionsToAllSelects(this)

			},

			hide: function (deleteElement) {
				if ($('#first-loading').length) {
					$(this).slideUp(deleteElement, function () {

						deleteElement()
						//   $('select.main-service-item').trigger('change');
					})
				}
				else {
					if (confirm('Are you sure you want to delete this element?')) {
						$(this).slideUp(deleteElement, function () {

							deleteElement()
							$('select.main-service-item').trigger('change')
						})
					}
				}

			}
		})
	}

	var demo4 = function () {

		$('#m_repeater_4').repeater({
			initEmpty: false,
			isFirstItemUndeletable: true,
			defaultValues: {
				'exchange_rate':1
			},

			show: function () {
				$(this).slideDown()
				$('input.trigger-change-repeater').trigger('change')

				appendNewOptionsToAllSelects(this)

			},

			hide: function (deleteElement) {
				if ($('#first-loading').length) {
					$(this).slideUp(deleteElement, function () {

						deleteElement()
						//   $('select.main-service-item').trigger('change');
					})
				}
				else {
					if (confirm('Are you sure you want to delete this element?')) {
						$(this).slideUp(deleteElement, function () {

							deleteElement()
							$('select.main-service-item').trigger('change')
							$('input.trigger-change-repeater').trigger('change')

						})
					}
				}
			}
		})
	}

	var demo5 = function () {
		$('#m_repeater_5').repeater({
			initEmpty: false,
			isFirstItemUndeletable: true,
			defaultValues: {
				'exchange_rate':1
			},

			show: function () {
				$(this).slideDown()
				$('input.trigger-change-repeater').trigger('change')
				appendNewOptionsToAllSelects(this)

			},

			hide: function (deleteElement) {
				if ($('#first-loading').length) {
					$(this).slideUp(deleteElement, function () {

						deleteElement()

						//   $('select.main-service-item').trigger('change');
					})
				}
				else {
					if (confirm('Are you sure you want to delete this element?')) {
						$(this).slideUp(deleteElement, function () {

							deleteElement()
							$('select.main-service-item').trigger('change')
						})
					}
				}
				$('input.trigger-change-repeater').trigger('change')


			}
		})
	}

	var demo6 = function () {
		$('#m_repeater_6').repeater({
			initEmpty: false,
			isFirstItemUndeletable: true,
			defaultValues: {
				'exchange_rate':1
			},

			show: function () {
				$('select.repeater-select').selectpicker('refresh')
				$(this).slideDown()
				appendNewOptionsToAllSelects(this)

			},

			hide: function (deleteElement) {
				if ($('#first-loading').length) {
					$(this).slideUp(deleteElement, function () {

						deleteElement()
						//   $('select.main-service-item').trigger('change');
					})
				}
				else {
					if (confirm('Are you sure you want to delete this element?')) {
						$(this).slideUp(deleteElement, function () {

							deleteElement()
							$('select.main-service-item').trigger('change')
						})
					}
				}
			}
		})
	}




	var demo7 = function () {
		$("#m_repeater_7").repeater({
			initEmpty: false,
			isFirstItemUndeletable: true,
			defaultValues: {
				'exchange_rate':1,
			},

			show: function () {
				$('select.repeater-select').selectpicker('refresh')
				$(this).slideDown()
			},

			hide: function (deleteElement) {
				if ($("#first-loading").length) {
					$(this).slideUp(deleteElement, function () {
						deleteElement()
						//   $('select.main-service-item').trigger('change');
					})
				} else {
					if (
						confirm("Are you sure you want to delete this element?")
					) {
						$(this).slideUp(deleteElement, function () {
							deleteElement()
							$("select.main-service-item").trigger("change")
						})
					}
				}
			},
		})
	}

	var demo8 = function () {
		$("#m_repeater_8").repeater({
			initEmpty: false,
			isFirstItemUndeletable: true,

			defaultValues: {
				'exchange_rate':1,
			},

			show: function () {
				
				$(this).find('.dropdown-toggle').remove();
				$(this).find('select.repeater-select').selectpicker("refresh");
				$(this).slideDown()
				appendNewOptionsToAllSelects(this)
				
			},

			hide: function (deleteElement) {
				if ($("#first-loading").length) {
					$(this).slideUp(deleteElement, function () {
						deleteElement()
						//   $('select.main-service-item').trigger('change');
					})
				} else {
					if (
						confirm("Are you sure you want to delete this element?")
					) {
						$(this).slideUp(deleteElement, function () {
							deleteElement()
							//$('select.main-service-item').trigger('change');
						})
					}
				}
			},
		})
	}
	
	
	var demo9 = function () {
		$("#m_repeater_9").repeater({
			initEmpty: false,
			isFirstItemUndeletable: true,

			defaultValues: {
				'exchange_rate':1,
			},

			show: function () {
				$(this).find('.dropdown-toggle').remove();
				$(this).find('select.repeater-select').selectpicker("refresh");
				$(this).slideDown()
			},

			hide: function (deleteElement) {
				if ($("#first-loading").length) {
					$(this).slideUp(deleteElement, function () {
						deleteElement()
						//   $('select.main-service-item').trigger('change');
					})
				} else {
					if (
						confirm("Are you sure you want to delete this element?")
					) {
						$(this).slideUp(deleteElement, function () {
							deleteElement()
							//$('select.main-service-item').trigger('change');
						})
					}
				}
			},
		})
	}
	
	
	
	var demo10 = function () {
		$('.m_repeater_outer').repeater({
			initEmpty: false,
			isFirstItemUndeletable: true,
			selector: '.inner-repeater',
			defaultValues: {
				'exchange_rate': 1
			},
			show: function () {
				$('select.repeater-select').selectpicker('refresh');
				$(this).slideDown();
				appendNewOptionsToAllSelects(this);
	
				// Initialize the inner repeater for the newly added row
				$(this).find('.m_repeater_inner').repeater({
					initEmpty: false,
					isFirstItemUndeletable: true,
					defaultValues: {
						'exchange_rate': 1
					},
					show: function () {
						$(this).find('.dropdown-toggle').remove();
						$(this).find('select.select2-select.suppliers-or-customers-js').selectpicker();
						$(this).slideDown();
						appendNewOptionsToAllSelects(this);
	
						// Stop propagation for the inner repeater's add button
						$(this).find('[data-repeater-create]').off('click').on('click', function (e) {
							e.stopPropagation();
						});
					},
					hide: function (deleteElement) {
						if ($('#first-loading').length) {
							$(this).slideUp(deleteElement, function () {
								deleteElement();
							});
						} else {
							if (confirm('Are you sure you want to delete this element?')) {
								$(this).slideUp(deleteElement, function () {
									deleteElement();
									$('select.main-service-item').trigger('change');
								});
							}
						}
					}
				});
			},
			hide: function (deleteElement) {
				if ($('#first-loading').length) {
					$(this).slideUp(deleteElement, function () {
						deleteElement();
					});
				} else {
					if (confirm('Are you sure you want to delete this element?')) {
						$(this).slideUp(deleteElement, function () {
							deleteElement();
							$('select.main-service-item').trigger('change');
						});
					}
				}
			}
		});
	};
	
	
	
	

	var demo11 = function () {
		$('.m_repeater_inner').repeater({
			initEmpty: false,
			isFirstItemUndeletable: true,
		
			defaultValues: {
				'exchange_rate':1
			},

			show: function () {
				$(this).find('.dropdown-toggle').remove();
				$(this).find('select.select2-select.suppliers-or-customers-js').selectpicker()
				$(this).slideDown()
				appendNewOptionsToAllSelects(this)
				// alert($(this).find('[data-repeater-create]').length)
				console.log($('[data-repeater-list="purchasesOrders"] [data-repeater-create]:eq(1)').length)
				$('[data-repeater-list="purchasesOrders"] [data-repeater-create]:eq(1)').off('click').on('click', function (e) {
					e.stopPropagation(); // Prevent the event from bubbling up to the outer repeater
				});
			},

			hide: function (deleteElement) {
				if ($('#first-loading').length) {
					$(this).slideUp(deleteElement, function () {

						deleteElement()
						//   $('select.main-service-item').trigger('change');
					})
				}
				else {
					if (confirm('Are you sure you want to delete this element?')) {
						$(this).slideUp(deleteElement, function () {

							deleteElement()
							$('select.main-service-item').trigger('change')
						})
					}
				}
			}
		})
	}



	return {
		// public functions
		init: function () {
			demo1()
			demo2()
			demo3()
			demo4()
			demo5()
			demo6()
			demo7()
			demo8()
			demo9()
			demo10()
			demo11()
		}
	}
}()

jQuery(document).ready(function () {
	FormRepeater.init()
});
