<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8"/>
  <title>HTML5 Doctor Appointment Scheduling (JavaScript/PHP)</title>

  <link type="text/css" rel="stylesheet" href="css/layout.css"/>

  <!-- DayPilot library -->
  <script src="js/daypilot/daypilot-all.min.js"></script>
</head>
<body>
<?php require_once '_header.php'; ?>

<div class="main">
  <?php require_once '_navigation.php'; ?>

  <div>

    <div class="column-left">
      <div id="nav"></div>
    </div>
    <div class="column-main">
      <div class="space">
        <select id="doctor" name="doctor"></select>
      </div>
      <div id="calendar"></div>
    </div>

  </div>
</div>

<script src="js/daypilot/daypilot-all.min.js"></script>

<script>
  var elements = {
    doctor: document.querySelector("#doctor")
  };

  var nav = new DayPilot.Navigator("nav");
  nav.selectMode = "week";
  nav.showMonths = 3;
  nav.skipMonths = 3;
  nav.onTimeRangeSelected = function (args) {
    loadEvents(args.start.firstDayOfWeek(), args.start.addDays(7));
  };
  nav.init();

  var calendar = new DayPilot.Calendar("calendar");
  calendar.viewType = "Week";
  calendar.timeRangeSelectedHandling = "Disabled";
  calendar.eventDeleteHandling = "Update";

  calendar.onEventMoved = function (args) {
    DayPilot.Http.ajax({
      url: "backend_move.php",
      data: args,
      success: function(ajax) {
        calendar.message(ajax.data.message);
      }
    });
  };
  calendar.onEventResized = function (args) {
    DayPilot.Http.ajax({
      url: "backend_move.php",
      data: args,
      success: function(ajax) {
        calendar.message(ajax.data.message);
      }
    });
  };
  calendar.onEventDeleted = function (args) {
    var params = {
      id: args.e.id(),
    };
    DayPilot.Http.ajax({
      url: "backend_delete.php",
      data: params,
      success: function (ajax) {
        calendar.message("Deleted.");
      }
    })
  };
  calendar.onBeforeEventRender = function (args) {
    if (!args.data.tags) {
      return;
    }
    switch (args.data.tags.status) {
      case "free":
        args.data.backColor = "#3d85c6";  // blue
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        break;
      case "waiting":
        args.data.backColor = "#e69138";  // orange
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        break;
      case "confirmed":
        args.data.backColor = "#6aa84f";  // green
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        break;
    }

  };

  calendar.onEventClick = function (args) {

    var form = [
      {name: "Edit Appointment"},
      {name: "Name", id: "text"},
      {name: "Status", id: "tags.status", options: [
          {name: "Free", id: "free"},
          {name: "Waiting", id: "waiting"},
          {name: "Confirmed", id: "confirmed"},
        ]},
      {name: "From", id: "start", dateFormat: "MMMM d, yyyy h:mm tt", disabled: true},
      {name: "To", id: "end", dateFormat: "MMMM d, yyyy h:mm tt", disabled: true},
      {name: "Doctor", id: "resource", disabled: true, options: doctors},
    ];

    var data = args.e.data;

    var options = {
      focus: "text"
    };

    DayPilot.Modal.form(form, data, options).then(function(modal) {
      if (modal.canceled) {
        return;
      }

      var params = {
        id: modal.result.id,
        name: modal.result.text,
        status: modal.result.tags.status
      };

      DayPilot.Http.ajax({
        url: "backend_update.php",
        data: params,
        success: function(ajax) {
          calendar.events.update(modal.result);
        }
      });
    });


  };
  calendar.init();

  function loadEvents(day) {
    var start = nav.visibleStart();
    var end = nav.visibleEnd();

    var params = {
      doctor: elements.doctor.value,
      start: start.toString(),
      end: end.toString()
    };

    DayPilot.Http.ajax({
      url: "backend_events_doctor.php",
      data: params,
      success: function(ajax) {
        var data = ajax.data;
        if (day) {
          calendar.startDate = day;
        }
        calendar.events.list = data;
        calendar.update();

        nav.events.list = data;
        nav.update();
      }
    });
  }

  elements.doctor.addEventListener("change", function() {
    loadEvents();
  });

  var doctors = [];
  DayPilot.Http.ajax({
    url: "backend_resources.php",
    success: function(ajax) {
      doctors = ajax.data;

      doctors.forEach(function(item) {
        var option = document.createElement("option");
        option.value = item.id;
        option.innerText = item.name;
        elements.doctor.appendChild(option);
      });

      loadEvents();

    }
  })

</script>

</body>
</html>
