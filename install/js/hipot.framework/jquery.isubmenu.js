/**
 * плагин выпадающего меню для сайта medicine tour
 * @version 1.0
 * @author hipot
 */
(function($) {
	$.fn.iSubMenu = function(options){
		var options = jQuery.extend({		
		}, options);
		
		return this.each(function(){
			/**
			 * текущее выделение, на него и плагин
			 */
			var menuContainer = $(this);
			
			/**
			 * текущий открытый пункт
			 */
			var currentOpenHead;						
			
			/**
			 * скрыть все подменю
			 */
			function hideAllPodMenus() {
				$(".pos-pod-tm ul.pod-tm", menuContainer).stop(true, true);
				//$(currentOpenHead).css('display', 'none');
				$(currentOpenHead).slideUp(80);
				$(".root", menuContainer).removeClass("hovered");
			};
			$("td", menuContainer).each(function(index){
				if ($('ul.pod-tm li', this).size() == 0) {
					$('ul.pod-tm', this).remove();
				}				
				
				$(this).bind("mouseenter", function(){						
					hideAllPodMenus();																
					var mi = this;
								
					if ($("ul.pod-tm", mi).size() > 0) {
						currentOpenHead = $("ul", mi);						
						$(currentOpenHead).addClass('pod-tm-right');	
						if (($(mi).offset().left + $(currentOpenHead).width() + 40) > $(window).width()) {
							$(currentOpenHead).addClass('pod-tm-right');	
						} else {
							$(currentOpenHead).removeClass('pod-tm-right');	
						}
																	
						$(currentOpenHead).delay(80).slideDown(100, "swing");						
					}
					$(".root", mi).addClass("hovered");		
				}).bind("mouseleave", function(){			
					hideAllPodMenus();	
				});
				if ($('ul.pod-tm .selected', this).size() > 0) {
					$(".root", this).addClass("selected");
					
				}
			});		    
		});
	};
})(jQuery);
