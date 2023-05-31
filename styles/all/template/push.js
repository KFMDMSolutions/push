
const registration = await navigator.serviceWorker.register('./firebase-messaging-sw.js')

const messaging = firebase.messaging();

;(function($, window, document) {
	if ('serviceWorker' in navigator){
		var notificationButtonHTML = "<div class='push-notify'><span>Browser notifications:</span><span class='push-toggle'><span></span></span><span class='push-notify-status'></span></div>";


		$('.dropdown-contents .footer')
			.after("<div class='footer'>" + notificationButtonHTML + "</div>");
		

		var $pushButton = $('.push-notify'), $pushStatus = $('.push-notify-status');
		var isSubscribed = false;
		
		isRegisteredUser();

		
		$pushButton.on('click', subscribe);
		if(isUserLoggedin()){
		}
	}

	function requestPermission() {
		Notification.requestPermission().then((permission) => {
			if (permission === 'granted') {
				messaging.getToken( {
					serviceWorkerRegistration: registration,
					vapidKey: vapidKey })
				.then((token) =>{
					if(token){
						console.log(token);	
						// Send an AJAX call to the controller
						// to add the user_id and this token
						// This way when the user needs to be notified
						// we just gather all of the tokens associated with this user's id
						// and send it
						$.post(firebase_user_url, {
							"user_id": isUserLoggedin(),
							"firebase_token": token
						}).then(function(data){
							console.log(data);
							isSubscribed = true;
							updateBtn();
						});
					}else {
						// Show permission request UI
						console.log('No registration token available. Request permission to generate one.');
					}
				}).catch((err)=> {
					messaging.deleteToken();
					//console.log('Unable to get permission to notify.', err);
				});
				
				messaging.onMessage((payload)=> {	
					if(isUserLoggedin() == payload.data.user_id){
						console.log('onMessage', payload);
						const notificationTitle = payload.data.title;
						const notificationOptions = {
							body: payload.data.body,
							icon: payload.data.icon,
							dir: payload.data.dir,
							data: {
								url: payload.data.url,
							},
						};
						navigator.serviceWorker.ready.then((registration1) => {
							registration1.showNotification(notificationTitle, notificationOptions);
						});
					}else{
						console.log('You are not Logged in');
					}
				});
			}
		});
	}

	function unsubscribe() {	
		if (Notification.permission == "granted"){
			messaging.getToken( {
					serviceWorkerRegistration: registration,
					vapidKey: vapidKey })
			.then((token) =>{
				if(token){
					//console.log(token);
					$.post(firebase_unsubscribe_url, {
					  "user_id": isUserLoggedin(),
					  "firebase_token": token
					}).then(function(data){
						console.log('unsubscribe ' +data);
						isSubscribed = false;					  
						updateBtn();	  
					});			
				} else {
					// Show permission request UI
					console.log('No registration token available. Request permission to generate one.');
				}
			}).catch((err)=> {
				//console.log('Unable to get permission to notify.', err);
			});	
			messaging.deleteToken();
		}
	}
	function subscribe() {
		if (isSubscribed) {
			unsubscribe();
		}
		else {
			requestPermission();
		}
	}
	function updateBtn() {
		if (Notification.permission === 'denied') {
			setButtonState('disallowed');
			return;
		}
		if (isSubscribed) {
			setButtonState('enabled');
		} else {
			setButtonState('disabled');
		}
	}

	function isUserLoggedin(){		
		var response = $.ajax({
			url:   push_firebase_is_user_loggedin,
			async: false,
			dataType: 'json',
			success: function (json) {
				response = json;
			},
			type:  'get'
		});    
		return response.responseJSON;
	}
	
	function isRegisteredUser(){
		if (Notification.permission == "granted"){
			messaging.getToken( {
					serviceWorkerRegistration: registration,
					vapidKey: vapidKey })
			.then((token) =>{
				if(token){
					//console.log(token);
					$.post(push_firebase_is_registered_user, {
					  "user_id": isUserLoggedin(),
					  "firebase_token": token
					}).then(function(data){
						if(data = 'registered'){
							//console.log('data = ' +data);
							requestPermission();
						//isSubscribed = false;					  
						//updateBtn();
						}						
					});			
				} else {
					// Show permission request UI
					console.log('No registration token available. Request permission to generate one.');
				}
			}).catch((err)=> {
				//console.log('Unable to get permission to notify.', err);
			});	
		}
		
		
		// var response = $.ajax({
			// url:   push_firebase_is_registered_user,
			// async: false,
			// dataType: 'json',
			// success: function (json) {
				// response = json;
			// },
			// type:  'get'
		// });    
		// return response.responseJSON;
	}
		
	function setButtonState(state) {
		switch (state) {
			case 'enabled':
				$pushButton.removeClass('push-toggle-locked').addClass('push-toggle-enabled');
				break;
			case 'disabled':
				$pushButton.removeClass('push-toggle-locked push-toggle-enabled');
				break;
			case 'disallowed':
				$pushButton.addClass('push-toggle-locked').removeClass('push-toggle-enabled')
				break;
			// case 'unsupported':
				// $pushStatus.text(pushNotifications.language.ACTIONS.UNSUPPORTED);
				// $pushButton.addClass('push-toggle-locked').removeClass('push-toggle-enabled')
					// .attr('title', pushNotifications.language.UNSUPPORTED);
				// break;
		}
	}

})(jQuery, window, document);


	
