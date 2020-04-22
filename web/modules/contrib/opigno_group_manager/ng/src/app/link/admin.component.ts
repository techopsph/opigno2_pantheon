import { Component, Input, Output, EventEmitter, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute } from '@angular/router';

import * as globals from '../app.globals';
import { AppService } from '../app.service';
import { EntityService } from '../entity/entity.service';
import { Entity } from '../entity/entity';
import { ActivitiesService } from '../activities/activities.service';
import { Activity } from "../activities/activity/activity";
import { Observable } from "rxjs/Observable";

@Component({
  selector: 'entity-link-admin',
  templateUrl: './admin.component.html',
  styleUrls: ['./link.component.scss']
})

export class LinkAdminComponent implements OnInit {
  @Input('selectedLink') selectedLink;
  @Input('entities') entities: Entity[];
  @Input('groupId') groupId: number;
  @Input('apiBaseUrl') apiBaseUrl: number;

  @Output() closed: EventEmitter<string> = new EventEmitter();
  @Output() deleted: EventEmitter<string> = new EventEmitter();

  activities: Activity[];
  errorMessage: string = '';
  scoreMessage: string = '';
  minScore: number;
  requiredActivities: any = [];
  requiredActivitiesNames: any = [];
  updateEntityLinkUrl: string;
  removeEntityLinkUrl: string;
  confirmCreateOrphan = false;
  conditionTypes: any;
  condition: string;
  conditionExists: boolean;
  conditionsExist: boolean;
  addCondition: boolean = false;
  showScoreField: boolean = false;
  showActivitiesField: boolean = false;
  checkboxesMap: any = [];

  constructor(
    private http: HttpClient,
    private appService: AppService,
    private entityService: EntityService,
    private activityService: ActivitiesService,
    private route: ActivatedRoute
  ) { }

  ngOnInit(): void {
    this.minScore = this.selectedLink.score;
    this.requiredActivities = this.selectedLink.activities ? this.selectedLink.activities : [];
    this.updateEntityLinkUrl = window['appConfig'].updateEntityLinkUrl;
    this.removeEntityLinkUrl = window['appConfig'].removeEntityLinkUrl;

    this.setConditionExists();
    this.setConditionTypes();
    this.setActivitiesMap();
  }

  setActivitiesMap(): void {
    // Initial read activities.
    let parent = this.entityService.getEntityByCid(this.selectedLink.parent, this.entities);
    let activities = this.activityService.getRequiredActivities(parent.entityId);
    Observable.forkJoin([activities]).subscribe(results => {
      let activities = Object.keys(results[0]).map(function(key) { return results[0][key] });
      // Order by weight
      activities.sort(function(a, b) {
        return a.weight - b.weight;
      });

      this.activities = activities;

      this.setActivitiesList();
      this.setActivitiesCheckboxes();
    });
  }

  setActivitiesCheckboxes() {
    // Initial set activities checkboxes states.
    let checked: boolean;
    for (let i = 0; i < this.activities.length; i++) {
      checked = false;
      for (let j = 0; j < this.requiredActivities.length; j++) {
        if (this.requiredActivities[j] == this.activities[i].id) {
          checked = true;
        }
      }
      // Set activities.
      this.checkboxesMap.push({id: this.activities[i].id, checked: checked});

      // Activities answers.
      let answers = !!this.activities[i].answers ? this.activities[i].answers : [];
      if (answers.length > 0) {
        for (let k = 0; k < answers.length; k++) {
          checked = false;
          for (let l = 0; l < this.requiredActivities.length; l++) {
            if (this.requiredActivities[l] == answers[k].id) {
              checked = true;
            }
          }
          // Set activities answers.
          this.checkboxesMap.push({id: answers[k].id, checked: checked});
        }
      }
    }
  }

  setActivitiesList() {
    // Set activities/answers names list for conditions info-block.
    this.requiredActivitiesNames = [];
    for (let i = 0; i < this.activities.length; i++) {
      for (let j = 0; j < this.requiredActivities.length; j++) {
        if (this.requiredActivities[j] == this.activities[i].id) {

          let answers = !!this.activities[i].answers ? this.activities[i].answers : [];
          let required_answers = [];
          if (answers.length > 0) {
            for (let k = 0; k < answers.length; k++) {
              for (let l = 0; l < this.requiredActivities.length; l++) {
                if (this.requiredActivities[l] == answers[k].id) {
                  required_answers.push(answers[k].text);
                }
              }
            }
          }

          this.requiredActivitiesNames.push({'name': this.activities[i].name, 'answers': required_answers});
        }
      }
    }
  }

  addConditionInput(type: string = null): void {
    // Set visibility flags.
    this.addCondition = true;
    if (!type) {
      type = this.condition;
    }
    else {
      this.setEditingType(type);
    }

    if (type === 'score') {
      this.showScoreField = true;
      this.showActivitiesField = false;
    }
    else if (type === 'activities') {
      this.showScoreField = false;
      this.showActivitiesField = true;
    }
  }

  validateCondition(): void {
    // Update required activities array.
    this.requiredActivities = [];
    for (let i = 0; i < this.checkboxesMap.length; i++) {
      if (this.checkboxesMap[i].checked) {
        this.requiredActivities.push(this.checkboxesMap[i].id);
      }
    }

    // Save conditions values.
    let child = this.entityService.getEntityByCid(this.selectedLink.child, this.entities);
    let json = {
      childCid: child.cid,
      parentCid: this.selectedLink.parent,
      requiredScore: this.minScore,
      requiredActivities: this.requiredActivities
    };

    if (this.validateScore(this.minScore)) {
      this.http
      .post(this.apiBaseUrl + this.appService.replaceUrlParams(this.updateEntityLinkUrl, {'%groupId': this.groupId}), JSON.stringify(json))
      .subscribe(data => {
        this.selectedLink.score = this.minScore;
        this.selectedLink.activities = this.requiredActivities;

        this.errorMessage = '';
        this.scoreMessage = '';

        // Set visibility flags.
        this.showScoreField = false;
        this.showActivitiesField = false;
        this.addCondition = false;
        this.setConditionExists();
        this.setConditionTypes();
        this.setActivitiesList();
      }, error => {
        console.error(error);
      });
    } else {
      this.scoreMessage = 'The score must be an integer between 0 and 100';
    }
  }

  deleteScoreCondition(): void {
    this.minScore = 0;
    this.validateCondition();
    this.setConditionTypes();
  }

  deleteActivitiesCondition(): void {
    for (let i = 0; i < this.checkboxesMap.length; i++) {
      this.checkboxesMap[i].checked = false;
    }
    this.validateCondition();
    this.setConditionTypes();
  }

  getChecked(id): boolean {
    // Activities checkboxes checked property.
    for (let i = 0; i < this.checkboxesMap.length; i++) {
      if (this.checkboxesMap[i].id == id) {
        return this.checkboxesMap[i].checked;
      }
    }
    return false;
  }

  checkboxChanged(values:any): void {
    // Update activities checkboxes mapping.
    let checked = values.currentTarget.checked;
    let value = values.currentTarget.value;
    let setParent: string = '';
    let setChild: string = '';
    let split: any;
    for (let i = 0; i < this.checkboxesMap.length; i++) {
      if (this.checkboxesMap[i].id == value) {
        this.checkboxesMap[i].checked = checked;
        if (checked && this.checkboxesMap[i].id.indexOf('-') !== -1) {
          split = this.checkboxesMap[i].id.split('-');
          setParent = split[0];
        }
        if (this.checkboxesMap[i].id.indexOf('-') === -1) {
          setChild = this.checkboxesMap[i].id;
        }
      }
    }
    if (setParent) {
      for (let i = 0; i < this.checkboxesMap.length; i++) {
        if (this.checkboxesMap[i].id == setParent) {
          this.checkboxesMap[i].checked = true;
        }
      }
    }
    if (setChild) {
      for (let i = 0; i < this.checkboxesMap.length; i++) {
        if (this.checkboxesMap[i].id.indexOf('-') !== -1) {
          split = this.checkboxesMap[i].id.split('-');
          if (split[0] == setChild) {
            this.checkboxesMap[i].checked = checked;
          }
        }
      }
    }
  }

  setConditionExists(): void {
    // Set conditions exist flag.
    if (this.minScore > 0 || (this.requiredActivities && this.requiredActivities.length > 0)) {
      this.conditionExists = true;
    }
    else {
      this.conditionExists = false;
    }

    if (this.minScore > 0 && (this.requiredActivities && this.requiredActivities.length > 0)) {
      this.conditionsExist = true;
    }
    else {
      this.conditionsExist = false;
    }
  }

  isScoreConditionExists(): boolean {
    if (this.selectedLink.score && this.selectedLink.score > 0) {
      return true;
    }
    return false;
  }

  isActivitiesConditionExists(): boolean {
    if (this.selectedLink.activities && this.selectedLink.activities.length > 0) {
      return true;
    }
    return false;
  }

  setConditionTypes(): void {
    // Set conditions dropdown options.
    this.conditionTypes = [];
    if (this.minScore == 0 || this.minScore === null) {
      this.conditionTypes.push({id: "score", name: "Score"});
      this.condition = 'score';
    }

    if (!this.requiredActivities || this.requiredActivities.length === 0) {
      this.conditionTypes.push({id: "activities", name: "Answer at last step"});
    }

    if (this.minScore > 0) {
      this.condition = 'activities';
    }
  }

  setEditingType(type): void {
    // Set conditions dropdown options for edit mode.
    this.conditionTypes = [];
    if (type === 'score') {
      this.conditionTypes.push({id: "score", name: "Score"});
    }
    else {
      this.conditionTypes.push({id: "activities", name: "Answer at last step"});
    }
    this.condition = type;
  }

  private validateScore(score): boolean {
    let isValid = false;
    if (score >= 0 && score <= 100) {
      isValid = true;
    }
    return isValid;
  }

  private isCreateOrphan(): boolean {
    let isCreateOrphan = false;
    let child = this.entityService.getEntityByCid(this.selectedLink.child, this.entities);
    if (child.parents.length == 1) {
      isCreateOrphan = true;
    }
    return isCreateOrphan;
  }

  delete() {
    if (this.isCreateOrphan() && !this.confirmCreateOrphan) {
      this.confirmCreateOrphan = true;
    } else {
      let child = this.entityService.getEntityByCid(this.selectedLink.child, this.entities);
      let json = {
        childCid: child.cid,
        parentCid: this.selectedLink.parent
      };

      this.http
        .post(this.apiBaseUrl + this.appService.replaceUrlParams(this.removeEntityLinkUrl, { '%groupId': this.groupId }), JSON.stringify(json))
        .subscribe(data => {
          this.deleted.emit(this.selectedLink);
        }, error => {
          console.error(error);
          this.close();
        });
    }
  }

  cancel(): void {
    if (this.addCondition) {
      this.errorMessage = '';
      this.scoreMessage = '';

      this.addCondition = false;
      this.showScoreField = false;
      this.showActivitiesField = false;
      this.setConditionTypes();
    }
    else {
      this.close();
    }
  }

  close(): void {
    this.closed.emit(null);
  }
}
